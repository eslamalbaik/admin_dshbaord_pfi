#!/usr/bin/env bash
# Mirrors a monorepo subdirectory into its standalone GitHub repo, following the
# convention documented in CLAUDE.md:
#   - development: one squashed commit per sync
#   - deploy-new:   replays each monorepo commit touching the subdir, keeping its
#                    original commit message
#
# A commit's tree pushed to the mirror is always an exact copy of
# `git rev-parse <commit>:<subdir>` — never a hand-copy of files.
#
# Usage: mirror-sync.sh <subdir> <remote-name> <marker-tag-name>
set -euo pipefail

SUBDIR=$1        # e.g. arab-contractors-union-api
REMOTE=$2        # e.g. pcu-back
MARKER_TAG=$3    # e.g. mirror-api-deploy-new

git fetch "$REMOTE" development deploy-new

SHORT_SHA=$(git rev-parse --short HEAD)

# --- development: squashed sync ---------------------------------------------
DEV_TIP=$(git rev-parse "$REMOTE/development")
SUBDIR_TREE=$(git rev-parse "HEAD:$SUBDIR")
DEV_TREE=$(git rev-parse "$DEV_TIP^{tree}")

if [ "$SUBDIR_TREE" != "$DEV_TREE" ]; then
  NEW_DEV=$(git commit-tree "$SUBDIR_TREE" -p "$DEV_TIP" \
    -m "sync: mirror $SUBDIR from monorepo (through $SHORT_SHA)")
  git push "$REMOTE" "$NEW_DEV:refs/heads/development"
  echo "development: pushed $NEW_DEV"
else
  echo "development: tree unchanged, skipping"
fi

# --- deploy-new: replay individual commits ----------------------------------
if git rev-parse -q --verify "refs/tags/$MARKER_TAG" >/dev/null; then
  SINCE=$(git rev-parse "refs/tags/$MARKER_TAG")
else
  # First run for this mirror: only replay the commit that triggered this sync,
  # so we don't try to replay the entire monorepo history at once.
  SINCE=$(git rev-parse "HEAD^")
fi

COMMITS=$(git log --reverse --format=%H "$SINCE..HEAD" -- "$SUBDIR")

if [ -z "$COMMITS" ]; then
  echo "deploy-new: no new commits touching $SUBDIR, skipping"
  exit 0
fi

TIP=$(git rev-parse "$REMOTE/deploy-new")
LAST_SHA=$SINCE

for c in $COMMITS; do
  TREE=$(git rev-parse "$c:$SUBDIR")
  MSG=$(git log -1 --format=%B "$c")
  TIP=$(git commit-tree "$TREE" -p "$TIP" -m "$MSG")
  LAST_SHA=$c
done

git push "$REMOTE" "$TIP:refs/heads/deploy-new"
echo "deploy-new: pushed $TIP (through $LAST_SHA)"

git tag -f "$MARKER_TAG" "$LAST_SHA"
git push origin "refs/tags/$MARKER_TAG" -f

