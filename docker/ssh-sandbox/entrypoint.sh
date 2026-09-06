#!/bin/sh
# Entrypoint for the homie demo output-card SSH sandbox. Host keys are
# regenerated fresh on every container start (not persisted) - deliberate:
# clients connect with StrictHostKeyChecking=accept-new (see homie's
# MachineDiscovery::sshCommand()), so a rotating host key is harmless and
# avoids needing a persistent volume for something this low-stakes.
set -eu

# authorized_keys is baked into the image at build time with correct
# ownership/permissions already set - see the Dockerfile - nothing to copy
# or chown here, deliberately, so the running container never needs
# CAP_CHOWN (dropped along with every other capability it doesn't need).
if [ ! -f /etc/ssh/ssh_host_ed25519_key ]; then
    ssh-keygen -A
fi

exec /usr/sbin/sshd -D -e -f /etc/ssh/sshd_config
