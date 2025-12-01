# Connected-Capacity

## Database Setup

This Laravel application uses SQLite as its database. Before running migrations, you must create the database file.

### Quick Setup

Run the setup script:

```bash
./setup-database.sh
```

Or manually create the database:

```bash
mkdir -p database
touch database/database.sqlite
```

### Running the Application

After setting up the database:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

## Troubleshooting

### Database file does not exist error

If you see an error like:
```
Database file at path [.../database/database.sqlite] does not exist
```

This means the SQLite database file hasn't been created. Run the setup script or manually create the file as shown above.

### Path contains special characters

If your project path contains spaces or special characters (like colons), consider moving the project to a path without special characters, or ensure your `.env` file uses the correct path format.
