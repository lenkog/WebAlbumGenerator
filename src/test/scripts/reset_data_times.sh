#!/bin/bash
if [ -z "$1" ]; then
    >&2 echo "Specify input file"
    exit 1
fi
if [ -z "$2" ]; then
    >&2 echo "Specify directory to update"
    exit 1
fi
while read -r line; do
    path=${line:14}
    if [ -z "$path" ]; then continue; fi
    time=${line:0:13}
    fullpath=$2/$path
    if [ -f "$fullpath" -o -d "$fullpath" ]; then
        touch -t $time "$fullpath"
    fi
done < "$1"
