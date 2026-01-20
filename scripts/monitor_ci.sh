#!/usr/bin/env bash
set -euo pipefail

branch="${1:-$(git rev-parse --abbrev-ref HEAD)}"
interval="${INTERVAL:-30}"

if ! command -v gh >/dev/null 2>&1; then
  echo "gh CLI not found; install it or use a curl-based script." >&2
  exit 2
fi

repo="${REPO:-}"
if [[ -z "$repo" ]]; then
  repo=$(gh repo view --json nameWithOwner -q .nameWithOwner 2>/dev/null || true)
fi
if [[ -z "$repo" ]]; then
  origin=$(git remote get-url origin 2>/dev/null || true)
  # supports git@github.com:owner/repo.git and https://github.com/owner/repo.git
  repo=${origin#*github.com[:/]}
  repo=${repo%.git}
fi
if [[ -z "$repo" ]]; then
  echo "Unable to determine repo; set REPO=owner/repo." >&2
  exit 2
fi

# Find the latest workflow run for the branch and capture its head SHA.
head_sha=""
run_url=""
while [[ -z "$head_sha" ]]; do
  latest=$(gh api -H "Accept: application/vnd.github+json" \
    "/repos/${repo}/actions/runs?branch=${branch}&per_page=1" \
    --jq '.workflow_runs[0] | [.id,.head_sha,.html_url] | @tsv' || true)

  if [[ -z "$latest" ]]; then
    echo "No workflow runs found for branch ${branch}; retrying in ${interval}s..." >&2
    sleep "$interval"
    continue
  fi

  IFS=$'\t' read -r latest_id head_sha run_url <<<"$latest"

  if [[ -z "$head_sha" || "$head_sha" == "null" ]]; then
    echo "Latest run missing head SHA; retrying in ${interval}s..." >&2
    head_sha=""
    sleep "$interval"
    continue
  fi
done

echo "Monitoring CI for ${repo}@${branch} (sha=${head_sha})"
echo "Latest run: ${run_url}"

is_failure_conclusion() {
  case "$1" in
    failure|cancelled|timed_out|action_required) return 0 ;;
    *) return 1 ;;
  esac
}

while true; do
  # Get all runs for this head SHA (across workflows).
  run_lines=$(gh api -H "Accept: application/vnd.github+json" \
    "/repos/${repo}/actions/runs?branch=${branch}&per_page=100" \
    --jq ".workflow_runs[] | select(.head_sha == \"${head_sha}\") | [.id,.status,.conclusion,.html_url,.name] | @tsv")

  if [[ -z "$run_lines" ]]; then
    echo "No runs found for sha ${head_sha}; retrying in ${interval}s..." >&2
    sleep "$interval"
    continue
  fi

  any_incomplete=0
  any_failure=0

  while IFS=$'\t' read -r run_id status conclusion html_url name; do
    [[ -z "$run_id" ]] && continue

    # Check job-level failures early.
    job_lines=$(gh api -H "Accept: application/vnd.github+json" \
      "/repos/${repo}/actions/runs/${run_id}/jobs?per_page=100" \
      --jq '.jobs[] | [.name,.status,.conclusion] | @tsv')

    while IFS=$'\t' read -r job_name job_status job_conclusion; do
      [[ -z "$job_name" ]] && continue
      if is_failure_conclusion "${job_conclusion}"; then
        echo "FAIL: ${name} -> job '${job_name}' concluded ${job_conclusion}"
        echo "Run: ${html_url}"
        exit 1
      fi
    done <<<"$job_lines"

    if [[ "$status" != "completed" ]]; then
      any_incomplete=1
    else
      if is_failure_conclusion "${conclusion}"; then
        echo "FAIL: ${name} concluded ${conclusion}"
        echo "Run: ${html_url}"
        exit 1
      fi
    fi
  done <<<"$run_lines"

  if [[ "$any_incomplete" -eq 0 ]]; then
    # All runs completed without failures.
    echo "All workflows completed successfully for ${head_sha}."
    exit 0
  fi

  ts=$(date '+%Y-%m-%d %H:%M:%S')
  echo "${ts} - still running; next check in ${interval}s"
  sleep "$interval"
done
