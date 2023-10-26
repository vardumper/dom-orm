#!/bin/bash

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

printf "Committing as ${YELLOW}$(git config user.name) ${NC}/ ${YELLOW}$(git config user.email)${NC}\n"

PASS=true

CHANGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')

# early return
if [[ -z "$CHANGED_FILES" ]]; then
  # printf "${YELLOW}No .php files in this commit${NC}\n"
  exit 0;
fi

printf "Running pre commit hook\n"

# ecs
PHP_ECS="./vendor/bin/ecs"
HAS_PHP_ECS=false

if [ -x $PHP_ECS ]; then
    HAS_PHP_ECS=true
fi

if $HAS_PHP_ECS; then
# if ([ -x $PHP_ECS ] && [ -n "$CHANGED_FILES" ]); then
    printf "ECS start"
    # Get a list of files in the staging area
    FILES=` git status --porcelain | grep -e '^[AM]\(.*\).php$' | cut -c 3- | tr '\n' ' '`
    if [ -z "$FILES" ]; then
          echo "No PHP file changed in commit"
    else
        $PHP_ECS check ${FILES} --fix
        ret_code=$?
        if [[ $ret_code == 0 ]]; then
            echo
            touch .commit
            # See post-commit hook
        else
            # Different code than 0 means that there were unresolved fixes
            PASS=false
        fi
    fi
else
    echo ""
    echo "Please install easy-coding-standard, and php-code-sniffer"
    echo ""
    echo "  composer require --dev symplify/easy-coding-standard"
    echo ""
fi

# phpstan
PHP_STAN="./vendor/bin/phpstan"
if ([ -x $PHP_STAN ] && [ -n "$CHANGED_FILES" ]); then
    printf "PHPStan start"
    if $PHP_STAN analyse --memory-limit=1G $CHANGED_FILES; then
      # All good
      printf "${GREEN}PHPStan passed${NC}\n"
    else
      PASS=false
    fi
fi

if ! $PASS; then
  printf "pre commit hook ${RED}FAILED${NC}\n"
  exit 1
else
  printf "pre commit hook ${GREEN}SUCCEEDED${NC}\n"
  exit 0
fi