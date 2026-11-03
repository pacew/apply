#!/bin/bash
cd "$(dirname "$0")"
python3 pull_live_data.py "$@"
status=$?
echo
read -n 1 -s -r -p "Press any key to close..."
exit $status
