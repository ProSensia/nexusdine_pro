# NexusDine Pro - Installation Guide

## System Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- SSL Certificate (for PWA)
- 500MB disk space

## Step 1: Download and Extract
1. Download the NexusDine Pro package
2. Extract to your web server directory (e.g., `/var/www/html/nexusdine_pro/`)

## Step 2: Configure Database
1. Create a MySQL database:
   ```sql
   CREATE DATABASE nexusdine_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;