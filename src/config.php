<?php
declare(strict_types=1);

const APP_NAME = 'Syntree';
const DB_PATH = __DIR__ . '/../data/syntree.sqlite';
const SESSION_NAME = 'syntree_session';

const OAUTH_PROVIDERS = [
    'google' => [
        'label' => 'Google',
        'client_id_env' => 'SYNTREE_GOOGLE_CLIENT_ID',
        'client_secret_env' => 'SYNTREE_GOOGLE_CLIENT_SECRET',
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
        'scope' => 'openid email profile',
    ],
    'github' => [
        'label' => 'GitHub',
        'client_id_env' => 'SYNTREE_GITHUB_CLIENT_ID',
        'client_secret_env' => 'SYNTREE_GITHUB_CLIENT_SECRET',
        'auth_url' => 'https://github.com/login/oauth/authorize',
        'token_url' => 'https://github.com/login/oauth/access_token',
        'userinfo_url' => 'https://api.github.com/user',
        'email_url' => 'https://api.github.com/user/emails',
        'scope' => 'read:user user:email',
    ],
];

