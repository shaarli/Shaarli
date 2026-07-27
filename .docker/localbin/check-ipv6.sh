#!/bin/sh
# check-ipv6-nc.sh - sh + BusyBox nc probe for AF_INET6 bind to ::
# Exit codes:
# 0 = IPv6 usable (nc bound to :: successfully)
# 1 = IPv6 disabled via module/sysctl
# 2 = nc not available or does not support required options
# 3 = AF_INET6/socket bind not supported (bind failed)


# helper: read file if present
read_if_exists() {
  if [ -r "$1" ]; then
    cat "$1" 2>/dev/null
  else
    echo ""
  fi
}

# 1) kernel/module check
if [ -r /sys/module/ipv6/parameters/disable ]; then
  v=$(read_if_exists /sys/module/ipv6/parameters/disable)
  v=$(printf '%s' "$v" | tr -d '[:space:]')
  if [ "$v" = "1" ]; then
    printf 'IPv6 disabled: /sys/module/ipv6/parameters/disable=1\n' >&2
    exit 1
  fi
fi

# 1b) sysctl/global disable
if command -v sysctl >/dev/null 2>&1; then
  if sysctl -n net.ipv6.conf.all.disable_ipv6 >/dev/null 2>&1; then
    v=$(sysctl -n net.ipv6.conf.all.disable_ipv6 2>/dev/null)
    v=$(printf '%s' "$v" | tr -d '[:space:]')
    if [ "$v" = "1" ]; then
      printf 'IPv6 disabled: net.ipv6.conf.all.disable_ipv6=1\n' >&2
      exit 1
    fi
  fi
else
  v=$(read_if_exists /proc/sys/net/ipv6/conf/all/disable_ipv6)
  v=$(printf '%s' "$v" | tr -d '[:space:]')
  if [ "$v" = "1" ]; then
    printf 'IPv6 disabled: /proc/sys/net/ipv6/conf/all/disable_ipv6=1\n' >&2
    exit 1
  fi
fi

# 2) check for configured IPv6 addresses (informational)
if [ -r /proc/net/if_inet6 ]; then
  if [ -s /proc/net/if_inet6 ]; then
    has_addr=1
  else
    has_addr=0
  fi
else
  has_addr=0
fi

# 3) runtime probe using BusyBox nc
if ! command -v nc >/dev/null 2>&1; then
  printf 'nc not found in PATH\n' >&2
  exit 2
fi

# Choose a random ephemeral port in 49152-65535
now=$(date +%s 2>/dev/null || echo 0)
port=$(( (now % 16384) + 49152 ))

# Start nc listening bound to :: on chosen port, redirect output, run in background
# Use bracketless :: for -s; BusyBox nc accepts that form.
nc -l -s :: -p "$port" >/dev/null 2>&1 &
pid=$!
sleep 0.15

# If process exited quickly, capture its exit status
if ! kill -0 "$pid" >/dev/null 2>&1; then
  # process not running; reap and inspect exit code
  wait "$pid" 2>/dev/null
  rc=$?
  # Common rc: 1 for bind/address family errors; treat as AF_INET6 unsupported/bind failed
  printf 'nc listener exited immediately with code %d\n' "$rc" >&2
  exit 3
fi

# If still running, we succeeded in binding to ::; kill the listener and return success
kill "$pid" >/dev/null 2>&1 || true
# wait for it to exit to avoid zombies
wait "$pid" 2>/dev/null || true

if [ "$has_addr" -eq 1 ]; then
  printf 'IPv6 usable: nc bound to :: succeeded; addresses present\n'
else
  printf 'IPv6 usable: nc bound to :: succeeded; no configured addresses found\n'
fi
exit 0
