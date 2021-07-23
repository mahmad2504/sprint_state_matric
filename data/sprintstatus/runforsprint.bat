@docker  cp .env sprint_state_matric_container:\sprint_state_matric\
@docker exec -it -w /sprint_state_matric sprint_state_matric_container php artisan sprintstatus:sync --sprint=%1 --force=1
