#!/bin/bash
#
# WCMS project aliases
#
# Makes developering easier!

export dev_root="/vagrant"
export app_root="$dev_root/app"

alias dev="cd $dev_root"
alias app="cd $app_root"

##
# Git Flow shortcuts

##
# Starts a feature using a wizard!
# usage: gf-start
# example: gf-start (and enjoy the wizardlyness)
#
function gf-start {
  result=$(tempfile) ; chmod go-rw $result

  whiptail --title "Jira Story ID" --inputbox "JIRA Story ID (spaces will become hyphens):" 20 78 "wcms-" 2>$result
  JIRA=$(cat $result)
  JIRA=${JIRA//[" "]/-}
  if [ "$JIRA" != "" ]
  then
    whiptail --title "JIRA Story Title" --inputbox "Jira Story Title (spaces will become hyphens):" 20 78 "" 2>$result
    TITLE=$(cat $result)
    TITLE=${TITLE//[" "]/-}
    if [ "$TITLE" != "" ]
    then
      BRANCH="$JIRA-$TITLE"
      git flow feature start "$BRANCH"
    fi
  fi

  rm $result
}
alias gfs=gf-start

##
# Publishes a feature
# usage: gf-publish [feature name]
# example: gf-publish wcms-764-library-integration
#
alias gf-publish="git flow feature publish"
alias gfp=gf-publish

##
# Finishes a feature
# usage: gf-finish [feature name]
# example: gf-finish wcms-764-library-integration
#
alias gf-finish="git flow feature finish"
alias gff=gf-finish

##
# Tracks a feature - pulls it down to your machine so you can
# work on it
# usage: gf-track <feature name>
# example: gf-track wcms-764-library-integration
#
alias gf-track="git flow feature track"
alias gft=gf-track

##
# Merges a branch into current branch (default develop)
# usage: gf-merge [branch]
# Make sure you are on the right branch before you run this command
#
function gf-merge {
  git checkout ${1-develop}
  git pull
  git checkout -
  git merge ${1-develop}
}
alias gfm=gf-merge

##
# Displays the GitHub pull request URL for the current branch
# usage: gf-pull-request (and enjoy your pull request link)
# Note: This doesn't make a pull request, just links you to one.
#
function gf-pull-request {
  repo=`git config --local --list | grep remote.origin.url | sed -n "s/.*:\(.*\)\..*/\1/p"`
  branch="$(git symbolic-ref --short --quiet HEAD)"
  base="$(git config --local --get "gitflow.branch.$branch.base")"
  echo "https://github.com/$repo/compare/$base...$branch"
}
alias gfpr=gf-pull-request
