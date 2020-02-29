#!/bin/bash
if [ -z "$1" ]; then
    >&2 echo "Specify directory to process"
    exit 1
fi
if [ -z "$2" ]; then
    >&2 echo "Specify output file"
    exit 1
fi
find "$1" -printf "%Ty%Tm%Td%TH%TM.%TS %P\n" | cut --complement -c 14-24 > "$2"
