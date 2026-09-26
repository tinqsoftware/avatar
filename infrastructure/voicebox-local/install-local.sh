#!/bin/zsh
set -euo pipefail

script_dir="${0:A:h}"
runtime_dir="$HOME/Library/Application Support/AvatarIA/voicebox"
venv_dir="$runtime_dir/.venv"
plist_path="$HOME/Library/LaunchAgents/pe.tinq.avatar-voicebox.plist"

if ! command -v python3.11 >/dev/null 2>&1; then
  echo "Instala Python 3.11 (por ejemplo: brew install python@3.11) y vuelve a ejecutar este script."
  exit 1
fi

mkdir -p "$runtime_dir" "$HOME/Library/LaunchAgents"
python3.11 -m venv "$venv_dir"
"$venv_dir/bin/pip" install --upgrade pip
"$venv_dir/bin/pip" install -r "$script_dir/requirements.txt"

if [[ -z "${VOICEBOX_LOCAL_TOKEN:-}" ]]; then
  echo "Define VOICEBOX_LOCAL_TOKEN antes de ejecutar el instalador."
  exit 1
fi

cat > "$plist_path" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>pe.tinq.avatar-voicebox</string>
  <key>ProgramArguments</key><array><string>$venv_dir/bin/uvicorn</string><string>app:app</string><string>--host</string><string>127.0.0.1</string><string>--port</string><string>8790</string></array>
  <key>WorkingDirectory</key><string>$script_dir</string>
  <key>EnvironmentVariables</key><dict><key>VOICEBOX_LOCAL_TOKEN</key><string>$VOICEBOX_LOCAL_TOKEN</string></dict>
  <key>RunAtLoad</key><true/><key>KeepAlive</key><true/>
  <key>StandardOutPath</key><string>$runtime_dir/voicebox.log</string><key>StandardErrorPath</key><string>$runtime_dir/voicebox-error.log</string>
</dict></plist>
PLIST

launchctl bootout "gui/$(id -u)" "$plist_path" 2>/dev/null || true
launchctl bootstrap "gui/$(id -u)" "$plist_path"
echo "Voicebox local escuchando solo en http://127.0.0.1:8790"
