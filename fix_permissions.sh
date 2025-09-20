#!/bin/bash

# Script pour fixer les permissions en production

# Répertoires qui doivent être accessibles en écriture
chmod 755 storage/
chmod 755 storage/uploads/
chmod 755 storage/reports/
chmod 755 storage/logs/

# Répertoires en lecture seule
chmod 755 public/
chmod 755 src/
chmod 755 vendor/

# Fichiers exécutables
chmod 644 public/index.php
chmod 644 public/*.html
chmod 644 public/*.js

echo "Permissions fixées pour la production"