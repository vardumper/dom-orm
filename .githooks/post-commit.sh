#!/bin/bash

if [ -x ".commit" ]; then
    echo "Deleting temporary file .commit, and amending fixed files to commit"
    rm .commit
    git add yourfile
    git commit --amend -C HEAD --no-verify
fi