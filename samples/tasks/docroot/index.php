<?php
require_once 'config.php';

require __DIR__.'/../classes/User.php';

$session = outlet\Outlet::openSession();

// Create
echo "Creating...\n";
$user = new outlet\samples\model\User;
$user->ID = 1;
$user->Username = 'Test user';
$session->save($user)
	->clear(); // Clears cache
echo "Created\n";

// Load
echo "Loading...\n";
$user = $session->load('User', 1); // No need to specify namespaces
echo "Loaded\n";

// Update
echo "Updating...\n";
$user->Username = 'new name';
$user = $session->flush() // Autodetects update
	        ->clear()->load('User', 1); // Reloads from DB
echo "Updated\n";

// Delete
echo "Deleting...\n";
$session->delete($user);
echo "Deleted\n";