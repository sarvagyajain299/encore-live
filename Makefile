# Encore Live — make targets for WSL / Linux / macOS.
# Every target delegates to scripts/encore.sh so the logic lives in one place.
# On Windows PowerShell use: .\scripts\encore.ps1 <command>
#
#   make up && make install
#   make wp ARGS="plugin list"

ENCORE := ./scripts/encore.sh
ARGS ?=

.PHONY: up down install wp composer phpcs phpcbf test seed reset shell destroy help

up:       ; @$(ENCORE) up
down:     ; @$(ENCORE) down
install:  ; @$(ENCORE) install
wp:       ; @$(ENCORE) wp $(ARGS)
composer: ; @$(ENCORE) composer $(ARGS)
phpcs:    ; @$(ENCORE) phpcs $(ARGS)
phpcbf:   ; @$(ENCORE) phpcbf $(ARGS)
test:     ; @$(ENCORE) test $(ARGS)
seed:     ; @$(ENCORE) seed $(ARGS)
reset:    ; @$(ENCORE) reset $(ARGS)
shell:    ; @$(ENCORE) shell
destroy:  ; @$(ENCORE) destroy
help:     ; @$(ENCORE) help
