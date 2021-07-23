@set directory=//d/docker
@docker run -t -d -w /sprint_state_matric --name sprint_state_matric_container --volume %directory%:/sprint_state_matric/data/sprintstatus  mahmad2504/sprint_state_matric bash 
@update.bat