FROM alpine:3.20

RUN apk add --no-cache openssh-server \
    && adduser -D -s /bin/sh sandbox \
    && mkdir -p /home/sandbox/.ssh /run/sshd \
    # mkdir here runs as root (build time), so without an explicit chown the
    # directory stays root-owned even though sandbox owns everything else
    # under /home/sandbox - sshd's privsep child (a separate, non-root,
    # non-sandbox uid) needs real traverse+read access to reach
    # authorized_keys, which a root-owned 700 directory denies it.
    && chown sandbox:sandbox /home/sandbox/.ssh \
    && chmod 700 /home/sandbox/.ssh \
    # adduser -D sets a locked (`!`) shadow password, which this build's
    # PAM-less sshd treats as "invalid user" and refuses to authenticate at
    # all - even via pubkey with a forced command that never touches a real
    # shell. `*` still permits no password login, just not "invalid account".
    # Confirmed by hand: `!` -> "invalid user" in sshd's own debug log even
    # with a correct key; `*` -> pubkey auth succeeds normally. The shell is
    # `/bin/sh` (a real, listed-in-/etc/shells shell) for the same reason -
    # it's never actually invoked once a forced command is set, but an
    # invalid/unlisted shell was part of the same "invalid user" rejection.
    && sed -i 's/^sandbox:!:/sandbox:*:/' /etc/shadow

COPY sshd_config /etc/ssh/sshd_config
COPY allowed-command.sh /usr/local/bin/allowed-command.sh
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
# Baked in with correct ownership at build time (build-time root has full
# capabilities regardless of the running container's cap_drop) so the
# entrypoint never needs to chown/copy anything at runtime - the running
# container drops CAP_CHOWN along with everything else it doesn't need.
COPY --chown=sandbox:sandbox authorized_keys /home/sandbox/.ssh/authorized_keys
RUN chmod +x /usr/local/bin/allowed-command.sh /usr/local/bin/entrypoint.sh \
    && chmod 600 /home/sandbox/.ssh/authorized_keys

EXPOSE 2222

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
