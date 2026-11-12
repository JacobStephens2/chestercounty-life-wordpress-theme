#!/usr/bin/env bash
# Exercises the Travis integration setup: WordPress develop checkout URL
# and latest-version resolution. Stubs curl and git at the process boundary.
set -euo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
travis_file="$root/.travis.yml"

extract_integration_setup() {
	awk '
		/if \[\[ "\$INTEGRATION" == "1" \]\]; then/ { capture = 1 }
		capture {
			line = $0
			sub(/^    /, "", line)
			print line
		}
		capture && /^    fi$/ { capture = 0 }
	' "$travis_file"
}

assert_eq() {
	local actual="$1"
	local expected="$2"
	local message="$3"
	if [[ "$actual" != "$expected" ]]; then
		printf 'FAIL: %s\n  expected: %s\n  actual:   %s\n' "$message" "$expected" "$actual" >&2
		exit 1
	fi
}

assert_status() {
	local actual="$1"
	local expected="$2"
	local message="$3"
	if [[ "$actual" -ne "$expected" ]]; then
		printf 'FAIL: %s\n  expected status: %s\n  actual status:   %s\n' "$message" "$expected" "$actual" >&2
		exit 1
	fi
}

# Runs the integration setup with stubbed curl/git.
# Prints "<status>\n<git-args-or-empty>\n<curl-args-or-empty>".
run_setup() {
	local wp_version="$1"
	local curl_exit="$2"
	local response_body="$3"
	local work
	work=$(mktemp -d)
	local runner="$work/run.sh"
	local git_args="$work/git-args"
	local curl_args="$work/curl-args"
	local body="$work/body"
	printf '%s' "$response_body" > "$body"

	{
		echo '#!/usr/bin/env bash'
		echo 'set -eo pipefail'
		echo 'cd() { return 0; }'
		echo 'cp() { return 0; }'
		echo 'mysql() { return 0; }'
		echo 'curl() {'
		echo '  printf "%s\n" "$@" > "$CURL_ARGS_FILE"'
		echo '  if [[ "$CURL_EXIT" -ne 0 ]]; then'
		echo '    return "$CURL_EXIT"'
		echo '  fi'
		echo '  local dest="" prev=""'
		echo '  for arg in "$@"; do'
		echo '    if [[ "$prev" == "-o" ]]; then dest=$arg; fi'
		echo '    prev=$arg'
		echo '  done'
		echo '  if [[ -n "$dest" ]]; then'
		echo '    command cp "$CURL_BODY_FILE" "$dest"'
		echo '  else'
		echo '    cat "$CURL_BODY_FILE"'
		echo '  fi'
		echo '}'
		echo 'git() {'
		echo '  printf "%s\n" "$@" > "$GIT_ARGS_FILE"'
		echo '  exit 0'
		echo '}'
		echo 'printf "%s" "{\"offers\":[{\"version\":\"9.9.9\"}]}" > /tmp/wp-latest.json'
		extract_integration_setup
	} > "$runner"

	local status
	set +e
	CURL_ARGS_FILE="$curl_args" \
		CURL_EXIT="$curl_exit" \
		CURL_BODY_FILE="$body" \
		GIT_ARGS_FILE="$git_args" \
		INTEGRATION=1 \
		WP_VERSION="$wp_version" \
		bash "$runner" 2>"$work/stderr"
	status=$?
	set -e

	local git_text="" curl_text=""
	if [[ -f "$git_args" ]]; then
		git_text=$(cat "$git_args")
	fi
	if [[ -f "$curl_args" ]]; then
		curl_text=$(cat "$curl_args")
	fi
	rm -rf "$work"
	rm -f /tmp/wp-latest.json
	printf '%s\n%s\n--CURL--\n%s\n' "$status" "$git_text" "$curl_text"
}

assert_latest_accepts() {
	local version="$1"
	local output
	output=$(run_setup "latest" 0 "{\"offers\":[{\"php_version\":\"7.4\",\"version\":\"${version}\"},{\"version\":\"1.0.0\"}]}")
	assert_status "$(printf '%s\n' "$output" | head -n 1)" 0 "version ${version} is accepted"
	assert_eq "$(git_url_from_output "$output")" "https://develop.git.wordpress.org/" "version ${version} clones over HTTPS"
	assert_eq "$(branch_from_output "$output")" "$version" "version ${version} is the checkout branch"
}

assert_latest_rejects() {
	local label="$1"
	local body="$2"
	local output
	output=$(run_setup "latest" 0 "$body")
	assert_status "$(printf '%s\n' "$output" | head -n 1)" 1 "${label} fails the job"
	assert_eq "$(git_url_from_output "$output")" "" "${label} does not choose the checkout"
}

git_args_from_output() {
	local output="$1"
	printf '%s\n' "$output" | awk 'BEGIN { p = 1 } /^--CURL--$/ { p = 0 } p && NR > 1 { print }'
}

git_url_from_output() {
	git_args_from_output "$1" | awk 'NR == 4 { print }'
}

branch_from_output() {
	git_args_from_output "$1" | awk 'NR == 3 { sub(/^--branch=/, ""); print }'
}

curl_args_from_output() {
	local output="$1"
	printf '%s\n' "$output" | awk 'BEGIN { p = 0 } /^--CURL--$/ { p = 1; next } p { print }'
}

output=$(run_setup "4.5" 0 "")
assert_status "$(printf '%s\n' "$output" | head -n 1)" 0 "pinned WordPress 4.5 checkout exits 0"
assert_eq "$(git_url_from_output "$output")" "https://develop.git.wordpress.org/" "pinned checkout clones the HTTPS develop repository"
assert_eq "$(branch_from_output "$output")" "4.5" "pinned checkout uses the requested WordPress branch"

output=$(run_setup "latest" 22 '{"offers":[{"version":"7.1.4"}]}')
assert_status "$(printf '%s\n' "$output" | head -n 1)" 1 "a failed WordPress version check fails the job"
assert_eq "$(git_url_from_output "$output")" "" "a failed WordPress version check does not clone"
assert_eq "$(curl_args_from_output "$output" | grep -Fx 'https://api.wordpress.org/core/version-check/1.7/' || true)" "https://api.wordpress.org/core/version-check/1.7/" "latest resolution fetches the HTTPS version-check API"

output=$(run_setup "master" 22 "")
assert_status "$(printf '%s\n' "$output" | head -n 1)" 0 "the master checkout exits 0"
assert_eq "$(git_url_from_output "$output")" "https://develop.git.wordpress.org/" "the master checkout clones the HTTPS develop repository"
assert_eq "$(branch_from_output "$output")" "master" "the master checkout keeps its branch"
assert_eq "$(curl_args_from_output "$output")" "" "the master checkout does not resolve latest"

assert_latest_accepts "7.1.4"
large_ok='{"offers":[{"php_version":"7.4","version":"7.1.4"}'
for i in $(seq 1 400); do
	large_ok+=",{\"version\":\"6.0.${i}\"}"
done
large_ok+=']}'
output=$(run_setup "latest" 0 "$large_ok")
assert_status "$(printf '%s\n' "$output" | head -n 1)" 0 "a long version-check response is accepted"
assert_eq "$(branch_from_output "$output")" "7.1.4" "a long version-check response uses the first version"
assert_eq "$(git_url_from_output "$output")" "https://develop.git.wordpress.org/" "a long version-check response clones over HTTPS"

assert_latest_accepts "6.7"
assert_latest_accepts "10.11"
assert_latest_accepts "6.7-RC1"
assert_latest_accepts "6.7.1-beta2"

assert_latest_rejects "a version with a shell separator" '{"offers":[{"version":"7.1.4;id"},{"version":"7.1.4"}]}'
assert_latest_rejects "a command substitution" '{"offers":[{"version":"$(id)"}]}'
assert_latest_rejects "a command substitution in backticks" '{"offers":[{"version":"`id`"}]}'
assert_latest_rejects "a single number" '{"offers":[{"version":"7"}]}'
assert_latest_rejects "four numeric parts" '{"offers":[{"version":"1.2.3.4"}]}'
assert_latest_rejects "a dotted suffix" '{"offers":[{"version":"6.7-beta.1"}]}'
assert_latest_rejects "two hyphenated suffixes" '{"offers":[{"version":"6.7-beta-1"}]}'
assert_latest_rejects "a missing version field" '{"offers":[{"php_version":"7.4"}]}'
assert_latest_rejects "an empty response" ''

printf 'ok\n'
