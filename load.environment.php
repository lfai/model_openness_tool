<?php

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->usePutenv()->bootEnv(DRUPAL_ROOT . '/../.env', 'dev', ['test'], true);

