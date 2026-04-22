# Vaakai - Setup Instructions

## Project Overview
Vaakai (formerly SpeakSpace) is a universal comment portal built with Laravel 11 that allows users to discuss any public URL.

## Task 1 Completion Summary

### ✅ Completed Items

1. **Laravel 11 Installation**
   - Fresh Laravel 11 installation created
   - Project renamed to "Vaakai"

2. **Dependencies Installed**
   - `giorgiosironi/eris` (v1.1.0) - Property-based testing library
   - `pestphp/pest` (v3.8.6) - Testing framework
   - `guzzlehttp/guzzle` (v7.10.0) - HTTP client (already included)
   - `laravel/socialite` (v5.26.1) - OAuth authentication
   - `pusher/pusher-php-server` (v7.2.7) - Pusher PHP SDK

3. **Environment Configuration**
   - `.env` configured for local development (SQLite)
   - `.env.example` configured for production (MySQL 8, Redis, Pusher)
   - Added Pusher configuration variables
   - Added Google OAuth configuration variables

4. **Database Migrations Created**
   - ✅ `urls` table - Stores normalized URLs with metadata
   - ✅ `comments` table - Stores user comments with sentiment
   - ✅ `votes` table - Stores like/dislike votes
   - ✅ `reports` table - Stores abuse reports
   - ✅ `users` table - Updated for Google OAuth (removed password fields)

5. **Migrations Verified**
   - All migrations successfully run and tested with SQLite
   - Schema matches design document specifications

6. **Git Repository**
   - Repository initialized
   - Code pushed to: https://github.com/BijjaSagar/vaakai.git

## Server Deployment Instructions

### Prerequisites
- PHP 8.2 or higher
- MySQL 8.0
- Redis
- Composer
- Node.js & NPM

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/BijjaSagar/vaakai.git
   cd vaakai
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Update .env with your credentials**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=vaakai
   DB_USERNAME=your_mysql_username
   DB_PASSWORD=your_mysql_password

   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=your_redis_password
   REDIS_PORT=6379

   PUSHER_APP_ID=your_pusher_app_id
   PUSHER_APP_KEY=your_pusher_key
   PUSHER_APP_SECRET=your_pusher_secret
   PUSHER_APP_CLUSTER=mt1

   GOOGLE_CLIENT_ID=your_google_client_id
   GOOGLE_CLIENT_SECRET=your_google_client_secret
   ```

5. **Create MySQL database**
   ```bash
   mysql -u root -p
   CREATE DATABASE vaakai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```

6. **Run migrations**
   ```bash
   php artisan migrate
   ```

7. **Build assets**
   ```bash
   npm run build
   ```

8. **Start queue worker** (for background jobs)
   ```bash
   php artisan queue:work redis
   ```

## Database Schema

### Tables Created

1. **urls** - Stores normalized URLs and OpenGraph metadata
   - `url_hash` (unique) - MD5 hash of normalized URL
   - `slug` (unique) - 8-character identifier for discussion page
   - `title`, `description`, `thumbnail_url` - OG metadata
   - `comment_count` - Denormalized counter

2. **comments** - User comments with sentiment tagging
   - `url_id` - Foreign key to urls
   - `parent_id` - For nested replies (one level only)
   - `sentiment` - enum: positive, negative, neutral
   - `likes_count`, `dislikes_count` - Denormalized counters

3. **votes** - Like/dislike votes
   - Unique constraint on `(comment_id, ip_address)`
   - `vote_type` - enum: like, dislike

4. **reports** - Abuse reports
   - `reason` - enum: spam, hate, fake, other

5. **users** - Google OAuth users
   - `google_id` (unique)
   - `avatar` - Profile picture URL

## Next Steps

- Task 2: Implement URL normalization service
- Task 3: Create OpenGraph scraper service
- Task 4: Build discussion controllers and views
- Task 5: Implement commenting system
- Task 6: Add voting functionality
- Task 7: Implement real-time updates with Pusher

## Requirements Validated

This scaffolding addresses the following requirements:
- **Requirement 1.3**: Database structure for URL normalization and hashing
- **Requirement 2.3**: Slug generation support via url_hash column
- **Requirement 5.3**: Comment storage with sentiment and vote counts
- **Requirement 6.1**: Vote tracking with unique constraint
- **Requirement 10.1**: Report storage structure

## Notes

- Local development uses SQLite for convenience
- Production should use MySQL 8 as specified in requirements
- Redis is configured for cache, sessions, and queue
- Pusher is configured for real-time broadcasting
- All migrations include proper indexes and foreign key constraints
