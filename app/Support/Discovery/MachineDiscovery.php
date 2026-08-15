<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use App\Models\Machine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Turns a saved Machine into a list of web-reachable Docker containers, via
 * either the Docker Engine API directly or `docker ps`/`docker inspect` over
 * SSH. Extracted from ⚡machine-manager.blade.php unchanged in behavior —
 * see CLAUDE.md ("Discovery: host-network containers need an inspect
 * fallback", "Discovery: don't gate on published ports alone") for the
 * reasoning behind the Traefik-label-first, EXPOSE-port-fallback logic here.
 */
class MachineDiscovery
{
    public function viaDocker(Machine $machine): DiscoveryResult
    {
        $base = str_contains($machine->host, '://')
            ? rtrim($machine->host, '/')
            : 'http://'.$machine->host.':2375';

        try {
            $response = Http::timeout(5)->get($base.'/containers/json');

            if (! $response->successful()) {
                return new DiscoveryResult([], 'Docker API returned HTTP '.$response->status().'.');
            }

            $host = parse_url($base, PHP_URL_HOST) ?: $machine->host;

            $results = [];

            /** @var list<array<string, mixed>> $containers */
            $containers = $response->json() ?? [];

            foreach ($containers as $container) {
                $traefikHost = $this->extractTraefikHost($container['Labels'] ?? []);
                $name = ltrim($container['Names'][0] ?? $container['Id'], '/');
                $image = $container['Image'] ?? '';

                /** @var list<array{PublicPort?: int}> $ports */
                $ports = $container['Ports'] ?? [];

                $publicPort = null;

                foreach ($ports as $portEntry) {
                    if (! empty($portEntry['PublicPort'])) {
                        $publicPort = $portEntry['PublicPort'];

                        break;
                    }
                }

                if ($traefikHost) {
                    $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$traefikHost];

                    continue;
                }

                if ($publicPort) {
                    $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$host.':'.$publicPort];

                    continue;
                }

                // Host-network containers never appear in `Ports` (there's no mapping
                // to publish — the container's ports are the host's ports directly), so
                // fall back to whatever port the image itself declared via EXPOSE. Still
                // surface it with just the host if the image declares no port at all —
                // it's reachable, we just don't know at which port, so leave that for
                // the user to fill in when they add the card.
                if (($container['HostConfig']['NetworkMode'] ?? null) === 'host') {
                    $port = $this->hostNetworkPortViaDockerApi($base, $container['Id']);
                    $results[] = [
                        'name' => $name,
                        'image' => $image,
                        'url' => $port ? 'http://'.$host.':'.$port : 'http://'.$host,
                    ];
                }
            }

            $error = $results === []
                ? 'No web-reachable containers were found (no Traefik label or published port).'
                : null;

            return new DiscoveryResult($results, $error);
        } catch (\Throwable) {
            return new DiscoveryResult([], 'Could not reach the Docker API at '.$base.'.');
        }
    }

    private function hostNetworkPortViaDockerApi(string $base, string $containerId): ?int
    {
        try {
            $response = Http::timeout(5)->get($base.'/containers/'.$containerId.'/json');

            if (! $response->successful()) {
                return null;
            }

            return $this->firstExposedPort($response->json('Config.ExposedPorts') ?? []);
        } catch (\Throwable) {
            return null;
        }
    }

    public function viaSsh(Machine $machine): DiscoveryResult
    {
        $identityFile = null;

        if ($machine->ssh_private_key) {
            $identityFile = tempnam(sys_get_temp_dir(), 'homie-ssh-');
            file_put_contents($identityFile, rtrim($machine->ssh_private_key)."\n");
            chmod($identityFile, 0600);
        }

        try {
            $command = $this->sshCommand($machine, $identityFile, "docker ps --format '{{json .}}'");

            $result = Process::timeout(15)->run($command);

            if (! $result->successful()) {
                $stderr = trim($result->errorOutput());
                $error = $stderr !== ''
                    ? 'SSH discovery failed: '.$stderr
                    : 'SSH discovery failed (exit code '.$result->exitCode().').';

                return new DiscoveryResult([], $error);
            }

            $results = [];

            /** @var array<string, string> $needsPortLookup Container name => image, for host-network containers with no published port. */
            $needsPortLookup = [];

            foreach (preg_split('/\r?\n/', trim($result->output())) as $line) {
                if ($line === '') {
                    continue;
                }

                $container = json_decode($line, true);

                if (! is_array($container)) {
                    continue;
                }

                $traefikHost = $this->extractTraefikHost($container['Labels'] ?? '');
                $port = $this->parseDockerCliPort($container['Ports'] ?? '');
                $name = $container['Names'] ?? 'unknown';
                $image = $container['Image'] ?? '';

                if ($traefikHost) {
                    $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$traefikHost];

                    continue;
                }

                if ($port) {
                    $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$machine->host.':'.$port];

                    continue;
                }

                // Host-network containers never appear with a port in `docker ps`
                // (there's no mapping to publish), so fall back to whatever port the
                // image itself declared via EXPOSE, via a follow-up `docker inspect`.
                if (($container['Networks'] ?? '') === 'host') {
                    $needsPortLookup[$name] = $image;
                }
            }

            if ($needsPortLookup !== []) {
                $this->resolveHostNetworkPortsViaSsh($machine, $identityFile, $needsPortLookup, $results);
            }

            $error = $results === []
                ? 'No web-reachable containers were found (no Traefik label or published port).'
                : null;

            return new DiscoveryResult($results, $error);
        } catch (\Throwable $e) {
            return new DiscoveryResult([], 'SSH discovery failed: '.$e->getMessage());
        } finally {
            if ($identityFile && file_exists($identityFile)) {
                unlink($identityFile);
            }
        }
    }

    /**
     * Every container in $needsPortLookup ends up in $results — with a port if the
     * image declares one via EXPOSE, otherwise a bare host URL so it still shows up in
     * discovery for the user to fill in the port manually.
     *
     * @param  array<string, string>  $needsPortLookup  Container name => image.
     * @param  list<array{name: string, image: string, url: string}>  $results  Appended to by reference.
     */
    private function resolveHostNetworkPortsViaSsh(Machine $machine, ?string $identityFile, array $needsPortLookup, array &$results): void
    {
        $resolved = [];

        $inspectTargets = implode(' ', array_map(escapeshellarg(...), array_keys($needsPortLookup)));
        $command = $this->sshCommand(
            $machine,
            $identityFile,
            "docker inspect {$inspectTargets} --format '{{.Name}}::{{json .Config.ExposedPorts}}'"
        );

        try {
            $result = Process::timeout(15)->run($command);
        } catch (\Throwable) {
            $result = null;
        }

        if ($result?->successful()) {
            foreach (preg_split('/\r?\n/', trim($result->output())) as $line) {
                if (! str_contains($line, '::')) {
                    continue;
                }

                [$rawName, $json] = explode('::', $line, 2);
                $name = ltrim($rawName, '/');

                if (! isset($needsPortLookup[$name])) {
                    continue;
                }

                $port = $this->firstExposedPort(json_decode($json, true) ?? []);
                $resolved[$name] = true;
                $results[] = [
                    'name' => $name,
                    'image' => $needsPortLookup[$name],
                    'url' => $port ? 'http://'.$machine->host.':'.$port : 'http://'.$machine->host,
                ];
            }
        }

        foreach ($needsPortLookup as $name => $image) {
            if (isset($resolved[$name])) {
                continue;
            }

            $results[] = ['name' => $name, 'image' => $image, 'url' => 'http://'.$machine->host];
        }
    }

    /**
     * @param  array<string, mixed>  $exposedPorts  Docker's `Config.ExposedPorts` shape: {"1234/tcp": {}}.
     */
    private function firstExposedPort(array $exposedPorts): ?int
    {
        $firstKey = array_key_first($exposedPorts);

        if ($firstKey === null) {
            return null;
        }

        return (int) strtok((string) $firstKey, '/');
    }

    private function sshCommand(Machine $machine, ?string $identityFile, string $remoteCommand): string
    {
        $port = $machine->ssh_port ?: 22;
        $user = $machine->ssh_user ?: 'root';

        $options = [
            '-o BatchMode=yes',
            '-o StrictHostKeyChecking=accept-new',
            '-o UserKnownHostsFile=/dev/null',
            '-o LogLevel=ERROR',
            '-o ConnectTimeout=5',
            '-p '.$port,
        ];

        if ($identityFile) {
            $options[] = '-i '.escapeshellarg($identityFile);
            $options[] = '-o IdentitiesOnly=yes';
        }

        return sprintf(
            'ssh %s %s %s',
            implode(' ', $options),
            escapeshellarg($user.'@'.$machine->host),
            escapeshellarg($remoteCommand),
        );
    }

    private function parseDockerCliPort(string $ports): ?int
    {
        if (preg_match('/(\d+)->\d+\/(tcp|udp)/', $ports, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, string>|string  $labels  Docker Engine API gives an
     *                                                object of label => value;
     *                                                `docker ps --format json`
     *                                                gives a single comma-joined string.
     */
    private function extractTraefikHost(array|string $labels): ?string
    {
        $haystack = is_array($labels) ? implode(' ', $labels) : $labels;

        if (preg_match('/Host\(`([^`]+)`\)/', $haystack, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
