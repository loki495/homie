# Security Policy

This app's configuration can contain SSH private keys, API credentials, and arbitrary shell commands (output-card commands), so please report security issues privately rather than opening a public issue.

## Reporting a Vulnerability

Use GitHub's private vulnerability reporting:

1. Go to the [Security tab](../../security) of this repository.
2. Click **Report a vulnerability**.
3. Include as much detail as you can: steps to reproduce, affected version/commit, and potential impact.

You should receive an acknowledgement within a few days. Please don't disclose the issue publicly until it's been addressed.

## Scope

This is a personal-use, self-hosted application intended for LAN or authenticated-reverse-proxy deployment (see the README for deployment guidance). Reports involving authentication/session handling, SSH key storage or the `storage/ssh` sync, output-card shell command execution, or credential exposure in config export are especially appreciated.
