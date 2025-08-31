---
description: Repository Information Overview
alwaysApply: true
---

# Repository Information Overview

## Repository Summary
This repository contains a Dhikar App project with two main components: a Laravel-based API backend and a Flutter mobile application. The backend provides RESTful API services for authentication, profile management, and group functionality, while the Flutter app delivers a mobile interface for Quranic reading and dhikr tracking.

## Repository Structure
- **Backend (Root)**: Laravel 12 API backend with RESTful endpoints
- **wered/**: Flutter mobile application for Quranic reading and dhikr tracking

### Main Repository Components
- **Backend API**: Provides authentication, profile management, and group functionality
- **Flutter App**: Mobile interface with multilingual support (English/Arabic) and theme customization
- **Database**: Configured for SQLite in testing, configurable for production

## Projects

### Laravel Backend
**Configuration File**: composer.json

#### Language & Runtime
**Language**: PHP
**Version**: ^8.2
**Framework**: Laravel 12.x
**Package Manager**: Composer

#### Dependencies
**Main Dependencies**:
- laravel/framework: ^12.0
- laravel/sanctum: ^4.2
- laravel/tinker: ^2.10.1

**Development Dependencies**:
- phpunit/phpunit: ^11.5.3
- laravel/pint: ^1.24
- fakerphp/faker: ^1.23

#### Build & Installation
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

#### Testing
**Framework**: PHPUnit
**Test Location**: tests/ (Unit and Feature directories)
**Configuration**: phpunit.xml
**Run Command**:
```bash
php artisan test
```

### Flutter Mobile App (wered)
**Configuration File**: pubspec.yaml

#### Language & Runtime
**Language**: Dart
**Version**: SDK ^3.8.0
**Framework**: Flutter
**Package Manager**: pub

#### Dependencies
**Main Dependencies**:
- flutter: sdk
- flutter_localizations: sdk
- provider: ^6.1.1
- http: ^1.1.0
- google_fonts: ^6.2.1
- flutter_svg: ^2.2.0
- shared_preferences: ^2.2.2

**Development Dependencies**:
- flutter_test: sdk
- flutter_lints: ^6.0.0

#### Build & Installation
```bash
cd wered
flutter pub get
flutter run
```

#### Testing
**Framework**: Flutter Test
**Test Location**: test/
**Run Command**:
```bash
cd wered
flutter test
```

#### Features
- Multilingual support (English/Arabic)
- Dark/Light theme modes
- Dhikr tracking and management
- Quranic reading interface
- Group functionality for collaborative Quranic activities