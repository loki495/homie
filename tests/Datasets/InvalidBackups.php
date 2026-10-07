<?php

declare(strict_types=1);

$backup = ['version' => 1, 'groups' => [], 'ungrouped_cards' => [], 'machines' => []];
$versionError = 'That file is not a supported Homie backup. Its version is missing or unsupported.';
$cases = [
    'empty object' => ['{}', $versionError],
    'empty array' => ['[]', $versionError],
    'unrelated document' => ['{"name":"some other app"}', $versionError],
];

$withoutVersion = $backup;
unset($withoutVersion['version']);
$cases['missing version'] = [json_encode($withoutVersion), $versionError];

foreach ([null, 0, 2, '1', true, [], ['value' => 1]] as $index => $version) {
    $cases["invalid version {$index}"] = [json_encode(array_replace($backup, ['version' => $version])), $versionError];
}

foreach (['groups', 'ungrouped_cards', 'machines'] as $section) {
    $error = "That file is not a valid Homie backup. The {$section} section must be a list.";
    $missing = $backup;
    unset($missing[$section]);
    $cases["missing {$section}"] = [json_encode($missing), $error];

    foreach ([null, 'invalid', 42, false, ['name' => 'not a list']] as $index => $value) {
        $cases["invalid {$section} {$index}"] = [json_encode(array_replace($backup, [$section => $value])), $error];
    }
}

dataset('invalid backup documents', $cases);
