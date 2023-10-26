#!/bin/bash

FILE=.commit
MODIFIED_FILES=$(cat .commit)

if test -f "$FILE"; then
    echo "Amending fixed .php files to the current commit"
    rm $FILE
    git add $MODIFIED_FILES
    git commit --amend -C HEAD --no-verify
fi