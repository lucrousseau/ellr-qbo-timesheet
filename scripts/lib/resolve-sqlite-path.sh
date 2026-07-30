#!/bin/sh
# Resolves the SQLite database file path for a Laravel backend directory.
# Usage: resolve-sqlite-path.sh <backend_dir>
# Prints the absolute path on stdout and exits 0 when DB_CONNECTION is sqlite.
# Exits 1 when the stack does not use SQLite.

read_dotenv_var() {
  env_file="$1"
  key="$2"
  default="$3"

  if [ ! -f "$env_file" ]; then
    printf '%s' "$default"
    return
  fi

  value=$(
    grep -E "^[[:space:]]*${key}=" "$env_file" 2>/dev/null \
      | tail -1 \
      | sed 's/^[[:space:]]*[^=]*=//; s/^[[:space:]]*//; s/[[:space:]]*$//; s/^"\(.*\)"$/\1/; s/^'\''\(.*\)'\''$/\1/' \
      | tr -d '\r'
  )

  if [ -z "$value" ]; then
    printf '%s' "$default"
  else
    printf '%s' "$value"
  fi
}

resolve_sqlite_env_var() {
  var_name="$1"
  env_file="$2"
  default="$3"

  eval "shell_value=\${$var_name:-}"
  if [ -n "$shell_value" ]; then
    printf '%s' "$shell_value"
    return
  fi

  read_dotenv_var "$env_file" "$var_name" "$default"
}

resolve_sqlite_path() {
  backend_dir="$1"
  env_file="${backend_dir}/.env"
  connection=""
  db_database=""

  connection="$(resolve_sqlite_env_var DB_CONNECTION "$env_file" sqlite)"
  if [ "$connection" != "sqlite" ]; then
    return 1
  fi

  db_database="$(resolve_sqlite_env_var DB_DATABASE "$env_file" database/database.sqlite)"
  case "$db_database" in
    /*) printf '%s' "$db_database" ;;
    *) printf '%s' "${backend_dir}/${db_database}" ;;
  esac
}

if [ "${1:-}" = "" ]; then
  echo "Usage: resolve-sqlite-path.sh <backend_dir>" >&2
  exit 1
fi

resolve_sqlite_path "$1"
