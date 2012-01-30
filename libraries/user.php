<?php

//Loading an image file.
class User
{
	const ROLE_NEUTRAL = 0;
	const ROLE_EDITOR = 2;
	const ROLE_ADMIN = 2;

	public static function login()
	{
		$role = !empty($_SESSION['user_role']) ? $_SESSION['user_role'] : FALSE;

		$GLOBALS['config']['user_role'] = !empty($role) ? $role : 0;
	}

	public static function getLoggedInUserId()
	{
		return!empty($_SESSION['user_id']) ? $_SESSION['user_id'] : FALSE;
	}

	public static function setUserRole($role)
	{
		$role = intval($role);
		$_SESSION['user_role'] = $role;
		$GLOBALS['config']['user_role'] = $role;
	}

}

User::login();