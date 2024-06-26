#!/bin/bash

# Commit message must be in correct format
# https://semver.org/
# https://www.conventionalcommits.org/
# https://youtu.be/nOVZxZX5dx8

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# commit headline must be in the correct format
commit_msg_file=$(git rev-parse --git-dir)/COMMIT_EDITMSG

# Read the commit message from the COMMIT_EDITMSG file
commit_msg=$(cat "$commit_msg_file")

# Regular expression pattern for conventional commit format
pattern="^(build|wip|ci|docs|feat|fix|perf|refactor|style|test|chore|revert|release)(\(.+\))?: .{1,}"

# Check if the commit message matches the pattern
if [[ ! $commit_msg =~ $pattern ]]; then
    printf "${RED}Aborting. ${YELLOW}Your commit message is invalid.${NC}

Syntax:
${YELLOW}<type>${NC}(${YELLOW}<scope>${NC}): ${YELLOW}<subject>${NC}

    ${YELLOW}<type>${NC} can be one of
    build chore ci docs feat fix perf refactor revert style test

    ${YELLOW}<scope>${NC} is optional

    ${YELLOW}<subject>${NC} there must be a description of the change

    Find more on this topic here:
    - ${GREEN}https://semver.org/${NC}
    - ${GREEN}https://www.conventionalcommits.org/${NC}
    - ${GREEN}https://youtu.be/nOVZxZX5dx8${NC}
"
    exit 1
fi