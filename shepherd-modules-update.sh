#!/bin/bash
#
# This script automates the creation and updating of a subtree split
# in a second repository.
#
set -eu
IFS=$'\n\t'

source_repository=git@github.com:universityofadelaide/shepherd.git
source_branch=develop
source_dir=web/modules/custom/shepherd

# This is typically the name you will be releasing the sub modules as.
destination_dir_name=shepherd
destination_repository=git@github.com:universityofadelaide/shepherd-modules.git
destination_branch=develop

temp_repo=$(mktemp -d)
temp_branch=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w ${1:-8} | head -n 1)

# Checkout the old repository, make it safe and checkout a branch
git clone ${source_repository} ${temp_repo}
cd ${temp_repo}
git checkout ${source_branch}
git remote remove origin
git checkout -b ${temp_branch}

# Create the split, check it out and then push the temp branch up
sha1=$(splitsh-lite --prefix=${source_dir}:${destination_dir_name} --quiet)
git reset --hard ${sha1}
git remote add remote ${destination_repository}
git push -f -u remote ${temp_branch}:${destination_branch}

# Cleanup
cd /tmp
rm -rf ${temp_repo}
