# Demo output-card SSH sandbox — for review before deployment

This container exists so a public demo visitor can add a real "output card"
(homie's arbitrary-shell-command-over-SSH feature) and see it actually work,
without ever touching a real machine. It is intentionally built to do almost
nothing. Read this whole file before deploying it anywhere.

## What it can do

Exactly five things, and nothing else, regardless of what a connecting client
actually sends:

- `uptime`
- `df -h`
- `date`
- `whoami`
- `echo <text>` (text itself is passed straight to the `echo` binary via
  `exec`, never through a shell — see `allowed-command.sh`'s comment on why
  the extra metacharacter check there is defense-in-depth, not load-bearing)

Anything else — including any attempt at a real shell, `;`/`&&` chaining,
path traversal, or a completely different command — gets a fixed rejection
message and a non-zero exit. There is no fallback, no "advanced mode," no
way to reach a real shell from this container over SSH, period.

## Why it's safe to say that, not just claim it

Four independent layers, each sufficient on its own, stacked anyway:

1. **`sshd_config`**: password auth off, root login off, no TCP/agent/X11
   forwarding, no tunneling, no PTY, `AllowUsers sandbox` only, `Subsystem
   sftp /bin/false` (no file transfer). A misconfiguration here still can't
   grant a shell — see layer 2.
2. **`authorized_keys`**: the one key on this box has
   `command="/usr/local/bin/allowed-command.sh"` plus every individual
   `no-*` restriction and OpenSSH's `restrict` catch-all. Even if `sshd_config`
   were somehow bypassed or misread, this key can only ever invoke one fixed
   script — never the client's requested command directly.
3. **`allowed-command.sh`**: exact-string `case` matching against the fixed
   list above. `$SSH_ORIGINAL_COMMAND` (whatever the client actually asked
   for) is only ever compared, never evaluated, interpolated into a shell
   string, or passed to `sh -c`. Every branch `exec`s a real binary directly
   with fixed or already-validated arguments.
4. **Container/Docker-level** (see Task 4d in the plan — apply the same
   hardening as every other demo container when this gets wired into compose):
   non-root process where possible, `cap_drop: ALL`, `security_opt:
   [no-new-privileges:true]`, its own Docker network with **zero** route to
   anything else on `media` (not the other demo containers, not the host, not
   the internet beyond what sshd itself needs), no volumes, no Docker socket.
   Even a hypothetical full break of layers 1-3 lands in an empty, isolated
   container with nowhere to go.

## Key management

- The keypair is dedicated to this sandbox alone — never reused anywhere else.
- The **public** key is committed here (`authorized_keys`) — safe, that's
  what public keys are for.
- The **private** key is deliberately NOT committed to this repo. It needs
  to end up encrypted in the demo template's `machines.ssh_private_key`
  column (same encryption-at-rest homie already uses for every real
  machine's key) so a visitor's pre-seeded or self-created output card can
  actually connect. Wiring: **not done yet** — needs a small addition to
  `demo:build-template` (or a dedicated demo-seeding step) that reads the
  private key from an env var/mounted file and creates the corresponding
  `Machine` row. Do this as a follow-up once the container itself is
  approved, not before.
- Worst case if this specific private key ever leaked: an attacker could SSH
  in and run `uptime`. That's the entire blast radius — which is the whole
  point of building it this way.

## Host keys

Regenerated fresh on every container start (`ssh-keygen -A` in
`entrypoint.sh`), not persisted. Deliberate: homie's own SSH client already
connects with `StrictHostKeyChecking=accept-new` (see
`MachineDiscovery::sshCommand()`), so a rotating host key is harmless and
this avoids needing a persistent volume for something this low-stakes.

## Adversarially tested locally before writing this section (2026-09-06)

Built and ran this image directly, then tried to break it - full results
below, not just trusting the design on paper:

**Two real bugs found in the design, both fixed in the source here:**
1. `adduser -D` leaves a locked (`!`) shadow password. This build's sshd has
   no PAM support, and its own account-validity check treats `!` as
   "invalid user" and refuses login outright - correct key, correct forced
   command, still rejected, confirmed via `LogLevel DEBUG3`. Not a security
   gap (still fully rejected), just meant the sandbox didn't work at all
   until fixed. Fix: shadow password `*` instead (still permits no
   password login, isn't flagged as an invalid account) and a real listed
   shell (`/bin/sh`, never actually invoked once a forced command is set -
   `/bin/false` was part of the same rejection).
2. Connecting without specifying a remote command (an interactive-session
   attempt) left `$SSH_ORIGINAL_COMMAND` genuinely unset, and `set -u`
   crashed the script with a raw shell error instead of the intended clean
   rejection message - a real risk of leaking the script's own path to a
   client, even though the connection was still correctly refused either
   way. Fixed with a default-empty fallback.

**Two more real gaps found once actually testing under the intended
`cap_drop: ALL` (the container config this is deployed with, not just the
bare SSH-level testing above):**
3. sshd's own pre-auth privilege-separation step chroots into `/var/empty`
   before authentication even happens (this is sshd's own hardening, not
   something we added) - needs `CAP_SYS_CHROOT`, which `cap_drop: ALL` alone
   removes. Confirmed via the exact failure: `chroot("/var/empty"):
   Operation not permitted [preauth]`.
4. Past that, authentication itself failed too — `.ssh`'s directory was
   root-owned (a real Dockerfile bug: `mkdir` runs as root at build time,
   and only the *file* inside it had an explicit `--chown`, not the
   directory) — sshd's own unprivileged pre-auth process (a dedicated,
   non-root, non-`sandbox` system user) couldn't traverse into a root-owned
   `700` directory to read `authorized_keys` at all. Fixed by explicitly
   chowning the directory at build time alongside the file.

Once both were fixed, also needed `CAP_SETUID`/`CAP_SETGID` added back (not
a bug — sshd genuinely needs these to drop from root to the `sandbox` user
after a successful auth; there's no way around a real forced-command SSH
server needing this). Final capability set, confirmed by testing exactly
this configuration end-to-end: `cap_drop: ALL` +
`cap_add: [SYS_CHROOT, SETUID, SETGID]` — nothing broader, and every command
in the full adversarial battery below was re-run against this exact
configuration, not just the unrestricted build from earlier in this file.

**Everything else held up as designed, verified by actually trying:**
- All five allowed commands work correctly (`uptime`, `df -h`, `date`,
  `whoami`, `echo <text>`).
- A disallowed command is cleanly rejected.
- Command chaining (`;`, `&&`), backtick substitution, and `$()` subshells
  in an `echo` argument are all rejected outright.
- An interactive-session attempt (no command specified) is cleanly
  rejected (post-fix).
- Explicit PTY allocation (`-tt`) fails at the SSH protocol level.
- Remote port forwarding (`-R`, the unambiguous server-side-permission
  case) fails with "remote port forwarding failed" - `AllowTcpForwarding
  no` genuinely enforced, not just configured.
- SFTP/file transfer is rejected outright (`Subsystem sftp /bin/false`).
- A connection with a non-matching key is rejected normally (key auth is
  genuinely required, not accidentally bypassed by the forced-command setup).

## Status

- Added to `docker-compose.yml` as `output-sandbox` (`profiles: ["demo"]`,
  its own `sandbox-net` network shared only with `app` - no route to
  `mock-sonarr`/`mock-radarr`/`vite`/anything else, per the design above).
- The keypair was regenerated (the first one used above got deleted during
  cleanup before the Machine-row wiring happened) and re-verified against
  the exact final `cap_drop`/`cap_add` configuration - every test in this
  file passed against the actual keypair now committed here.
- **Not yet done**: wiring the private key into a seeded `Machine` row (the
  actual "try it in the UI" step) - the private key itself is being held
  for that follow-up, not committed anywhere.
- Not yet deployed to media.
