# TODO List for Fixing Forbidden Error on Root Path

- [x] Modify `config/packages/security.yaml` to add a public firewall for the root path `/`
- [x] Add a new `home` method in `src/Controller/SecurityController.php` that redirects to the login route (route defined via attribute, no need for routes.yaml)
- [x] Restart Docker containers to apply changes
- [x] Clear Symfony cache to ensure routes are recognized
- [x] Test accessing `http://127.0.0.1:8000/` to ensure it redirects to `/login` without Forbidden error
