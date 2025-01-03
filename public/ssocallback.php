<?php

// The Apache module "mod_auth_openidc" throws an error if the SSO redirect URI defined in the Apache configuration is accessed directly.
// To prevent this error in our main entry point (index.php), we have to create this dedicated route.
// This route handles incoming traffic from the SSO provider and redirects it back to the application.
header("Location: /index.php");

exit;
