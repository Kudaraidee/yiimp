#!/bin/sh
find $1 -name "*.png" | while read png
do
  echo "crushing $png"
  pngcrush -brute "$png" /tmp/temp.png
  mv -f /tmp/temp.png "$png"
done;
