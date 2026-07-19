#!/bin/sh

set -eu

script_dir=$(dirname "$0")
extension_root=$(CDPATH= cd "$script_dir/../.." && pwd)
contract_file="$extension_root/docs/phase-00-runtime-contract.md"

if [ ! -f "$contract_file" ]; then
    printf 'Missing runtime contract: %s\n' "$contract_file" >&2
    exit 1
fi

require_text() {
    expected=$1

    if ! grep -Fq -- "$expected" "$contract_file"; then
        printf 'Runtime contract is missing required text: %s\n' "$expected" >&2
        exit 1
    fi
}

require_text 'Target release: **EspoCRM 10.0.2 only**'
require_text '8913b2c0212f315105c7cfa008ef9adebb66d5ff'
require_text 'f6f561e3dacb6329891a83899fe07a75cbe0b9c8'
require_text '>=8.3.0 <8.6.0'
require_text '/opt/crm.cursurituv.ro'
require_text '**PROHIBITED**'
require_text '### 1. Module and client loading'
require_text '### 2. Routes and API actions'
require_text '### 3. Dependency injection and bindings'
require_text '### 4. Metadata, resources, and rebuild'
require_text '### 5. ACL composition'
require_text '### 6. Attachments and file storage'
require_text '### 7. Queued jobs'
require_text '### 8. PDF services'
require_text '### 9. Client library loading'
require_text '### 10. Install, upgrade, rebuild, and uninstall'
require_text 'There is no extension `AfterUpgrade.php` hook.'
require_text 'Runtime-only behavior remains visibly pending'

printf 'Phase 00 runtime contract checks passed.\n'
