#!/usr/bin/env bash
# Shown in the terminal when you open the codespace: finishes or shows the setup, then
# starts the website. Safe to run any time:  bash .devcontainer/status.sh
cd "$(dirname "$0")" || exit 0
STATE=.state
LOG="$STATE/setup.log"
mkdir -p "$STATE"

if [ -f "$STATE/done" ]; then
  bash start.sh
  echo "Website: Ports tab › 8000 (globe icon). Emails and sign-in codes: Ports tab › 8025."
  exit 0
fi

if [ -f "$STATE/running" ] && pgrep -f "devcontainer/setup.sh" >/dev/null; then
  echo "Setup is still running. Showing its progress (Ctrl+C only stops the view, not the setup)..."
  tail -n 30 -f "$LOG" --pid="$(pgrep -f 'devcontainer/setup.sh' | head -1)"
  [ -f "$STATE/done" ] && exit 0
fi

echo "Running the setup now (10–15 minutes the first time). Output is also saved to .devcontainer/$LOG"
bash setup.sh
