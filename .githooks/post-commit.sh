#!/bin/bash

FILE=.commit

if test -f "$FILE"; then
    MODIFIED_FILES=$(cat .commit)
    echo "Amending fixed .php files ($MODIFIED_FILES) to the current commit."
    rm $FILE # and removing temp file
    git add $MODIFIED_FILES # adding all (incl. fixed) files to the staging area
    git commit --amend -C HEAD --no-verify # --no-verify to avoid running pre-commit hook again
fi