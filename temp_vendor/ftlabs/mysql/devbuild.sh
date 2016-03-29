#!/bin/sh
phing build -f "$(dirname "$0")"/build.xml -Dws "$(dirname `readlink -f $0`)"
