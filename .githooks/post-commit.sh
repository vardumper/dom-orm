#!/bin/bash

FILE=.commit
MODIFIED_FILES=$(cat .commit)

if test -f "$FILE"; then
    echo "Deleting temporary .commit file, and amending fixed files to commit"
    rm $FILE
    git add $MODIFIED_FILES
    git commit --amend -C HEAD --no-verify
fi