#!/bin/bash

if [ -e .commit ]
    then
    rm .commit
    git add yourfile
    git commit --amend -C HEAD --no-verify
fi
exit