# CPD Tracker

A comprehensive web-based Continuing Professional Development (CPD) tracking system designed for professional services organizations. Built with PHP and MySQL, this multi-tenant SaaS platform enables organizations to track, manage, and report on professional development activities across teams and departments.

## 🌟 Features

### Core Functionality
- **CPD Entry Management** - Log professional development activities with titles, descriptions, hours, categories, and supporting documentation
- **File Attachments** - Upload supporting documents (PDF, Word, images, calendar files)
- **Import/Export** - Bulk import from CSV or .ics calendar files, export to CSV with filters
- **Comprehensive Reporting** - Filter and export CPD records by date range and category
- **Total Hours Tracking** - Automatic calculation of CPD hours across all entries

### Goal Management System
- **Individual Goals** - Set personal CPD targets for team members
- **Team Goals** - Create shared goals for entire teams with progress tracking
- **Department Goals** - Organization-wide goals for departments
- **Progress Tracking** - Real-time progress monitoring with visual indicators
- **Team Comparison** - Compare individual progress against teammates
- **Goal Templates** - Reusable goal templates for common CPD requirements
- **Deadline Alerts** - Automatic notifications for approaching and overdue goals

### Multi-Tenant Architecture
- **Organization Management** - Support for multiple organizations with isolated data
- **Subscription Plans** - Basic, Professional, and Enterprise tiers
- **Trial Management** - 30-day trial periods with automatic status tracking
- **Capacity Limits** - User limits per organization based on subscription plan
- **Organisation Admins** - Dedicated administrators per organization

### Hierarchical Structure
- **Organisations** - Top-level tenants with isolated data
- **Departments** - Organizational units within companies (e.g., Tax, Audit, Consulting)
- **Teams** - Smaller groups within departments
- **Users** - Individual members assigned to teams

### Role-Based Access Control

#### 👤 User (Default Role)
- Log and manage own CPD entries
- View assigned goals and track progress
- Upload supporting documentation
- Export personal CPD records
- Compare progress with teammates on team goals

#### 👨‍💼 Manager
- View team members' CPD entries
- Create and manage goals for team members
- Generate team reports with filters
- Archive/unarchive team members
- Export team CPD data
- Track team goal progress

#### 🤝 Partner
- Manage multiple departments and teams
- Oversee managers and their teams
- View department-wide analytics
- Create department-level goals
- Archive/unarchive users and managers
- Access cross-team reporting

#### 🔧 Organisation Admin
- Manage users within their organisation
- Oversee departments and teams
- View organisation-wide statistics
- Assign organisation-level permissions
- Limited to their organisation's data

#### 🛠️ System Admin
- Full system access across all organisations
- Create and manage organisations
- Set subscription plans and limits
- View SaaS metrics (MRR, ARR, churn)
- Access system-wide analytics
- Monitor trial expirations and capacity issues

### Advanced Features
- **User Archiving** - Soft-delete users while preserving their data
- **Bulk Operations** - Select multiple CPD entries for deletion or editing
- **Real-time Editing** - Modal-based editing of CPD entries
- **Team Leadership** - Assign multiple managers and partners to teams
- **Activity Tracking** - Audit trail of user activities
- **Goals Dashboard Widget** - Quick overview of active goals on main dashboard
- **Leaderboards** - Gamified team goal progress tracking

## 🏗️ Technology Stack

### Backend
- **PHP 7.4+** - Server-side logic
- **MySQL 8.0+** - Database with stored procedures
- **PDO** - Prepared statements for SQL injection prevention
- **Session Management** - Secure session handling with timeouts

### Frontend
- **HTML5 & CSS3** - Semantic markup and modern styling
- **JavaScript (ES6+)** - Interactive UI components
- **Responsive Design** - Mobile-friendly layouts
- **Modal Dialogs** - AJAX-based editing without page reloads

### Security Features
- **Password Hashing** - bcrypt algorithm
- **Prepared Statements** - SQL injection prevention
- **Input Validation** - Server-side validation
- **Session Security** - HttpOnly cookies, session regeneration
- **File Upload Validation** - MIME type and extension checking
- **User Isolation** - Row-level security for file downloads
- **XSS Prevention** - Output sanitization with htmlspecialchars

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 8.0 or higher (or MariaDB 10.5+)
- Apache or Nginx web server
- PHP Extensions:
  - PDO
  - pdo_mysql
  - fileinfo
  - mbstring
  - session

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/cpd_tracker.git
cd cpd_tracker
```

### 2. Configure Database Connection

Edit `includes/database.php`:

```php
$host = "localhost";
$dbname = "cpd_tracker";
$username = "your_database_user";
$password = "your_database_password";
```

### 3. Create Database and Import Schema

```bash
mysql -u root -p < database/schema.sql
```

The schema includes:
- Tables for users, organisations, departments, teams, CPD entries, goals
- Stored procedures for goal progress tracking
- Foreign key constraints for data integrity
- Indexes for query performance

### 4. Set Up File Uploads Directory

```bash
mkdir uploads
chmod 755 uploads
```

### 5. Configure Web Server

#### Apache
Ensure `.htaccess` is enabled and `mod_rewrite` is active.

#### Nginx
Add this to your site configuration:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

### 6. Create First Admin User

Visit `/admin_register.php` in your browser (only works when no admin exists):

```
https://yourdomain.com/admin_register.php
```

Or use SQL:

```sql
INSERT INTO users (username, email, password_hash, role_id) 
VALUES ('admin', 'admin@example.com', '$2y$10$...', 2);
```

### 7. Create First Organisation

As system admin, navigate to **Admin Panel → Manage Organisations** and create your first organisation.

## 📊 Database Schema

### Core Tables
- `organisations` - Multi-tenant organisations
- `departments` - Departments within organisations
- `teams` - Teams within departments
- `users` - User accounts with roles
- `roles` - Role definitions (user, manager, partner, admin)
- `cpd_entries` - CPD activity records
- `cpd_goals` - Goal definitions
- `goal_progress` - Individual progress tracking
- `goal_templates` - Reusable goal templates

### Relationship Tables
- `user_teams` - User-to-team assignments
- `team_managers` - Manager-to-team assignments
- `team_partners` - Partner-to-team assignments
- `department_partners` - Partner-to-department assignments
- `organisation_admins` - Organisation administrator assignments

### Key Features
- **Cascading Deletes** - Proper foreign key relationships
- **Soft Deletes** - User archiving with `archived` flag
- **Stored Procedures** - Automated goal progress calculation
- **Triggers** - Status updates for overdue goals

## 🎯 Usage Guide

### For Users

1. **Register** - Sign up with your organisation
2. **Log CPD Entries** - Add professional development activities
3. **Upload Documents** - Attach certificates or supporting files
4. **Track Goals** - Monitor your progress on assigned goals
5. **Export Records** - Download your CPD history as CSV
6. **Compare with Team** - See how you stack up against teammates

### For Managers

1. **View Team Dashboard** - See all team members' progress
2. **Set Goals** - Create individual or team goals
3. **Generate Reports** - Filter and export team CPD data
4. **Monitor Progress** - Track goal completion rates
5. **Manage Users** - Archive inactive team members

### For Partners

1. **Oversee Departments** - Manage multiple departments
2. **Create Department Goals** - Set organisation-wide targets
3. **Review Analytics** - View cross-department statistics
4. **Manage Leadership** - Assign managers to teams
5. **Archive Users** - Manage user lifecycle

### For System Admins

1. **Manage Organisations** - Create and configure tenants
2. **Monitor SaaS Metrics** - Track MRR, ARR, churn, growth
3. **Set Subscriptions** - Assign plans and limits
4. **View System Health** - Identify issues requiring attention
5. **User Management** - Global user administration

## 🔒 Security Considerations

### Implemented Security Measures
- ✅ Password hashing with bcrypt
- ✅ Prepared statements for all database queries
- ✅ Session timeout (30 minutes)
- ✅ HttpOnly and SameSite cookies
- ✅ File upload validation (MIME type and extension)
- ✅ User isolation for file downloads
- ✅ XSS prevention with output sanitization
- ✅ Input validation on all forms
- ✅ CSRF protection ready (tokens in place)

### Recommended Additional Security
- [ ] Enable HTTPS (SSL/TLS)
- [ ] Set up rate limiting
- [ ] Implement CSRF tokens on all forms
- [ ] Add 2FA for admin accounts
- [ ] Regular security audits
- [ ] Set up automated backups
- [ ] Configure firewall rules
- [ ] Enable PHP security settings (disable_functions, open_basedir)

## 🎨 Customization

### Changing Subscription Plans

Edit `includes/organisation_functions.php` to modify plan limits:

```php
function hasReachedUserLimit($pdo, $org_id) {
    // Customize logic here
}
```

### Adding CPD Categories

Modify the category dropdown in relevant files:
- `dashboard.php`
- `includes/auth.php` (validateCPDEntry function)
- Database: Consider adding a `categories` table

### Custom Goal Templates

Add templates via the database:

```sql
INSERT INTO goal_templates (name, description, target_hours, target_entries, duration_days)
VALUES ('Q1 CPD', 'Quarterly CPD target', 20, 5, 90);
```

## 📈 Reporting

### Available Reports
- **Personal CPD Summary** - Export your own records
- **Team Reports** - Manager view of team CPD
- **Department Reports** - Partner view of departments
- **Organisation Reports** - Admin view of entire organisation
- **Goal Progress Reports** - Track goal completion rates
- **System Metrics** - SaaS dashboard with MRR, ARR, growth

### Export Formats
- **CSV** - Comma-separated values for Excel
- **Filtered Exports** - Filter by date range and category

## 🐛 Troubleshooting

### File Upload Issues
```bash
# Check upload directory permissions
chmod 755 uploads
chown www-data:www-data uploads

# Check PHP settings
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

### Database Connection Errors
```bash
# Verify MySQL is running
systemctl status mysql

# Check credentials in database.php
# Ensure database exists
mysql -u root -p -e "SHOW DATABASES;"
```

### Session Issues
```bash
# Check session directory permissions
ls -la /var/lib/php/sessions

# Verify session settings in php.ini
grep session.save_path /etc/php/7.4/apache2/php.ini
```

## 🔄 Maintenance

### Regular Tasks
- **Backup Database** - Daily automated backups recommended
- **Clean Temp Files** - Clear old uploads if needed
- **Update Goal Progress** - Runs automatically via stored procedure
- **Archive Old Users** - Soft-delete inactive accounts
- **Monitor Capacity** - Check organisations approaching limits

### Updating
```bash
# Pull latest changes
git pull origin main

# Run any database migrations
mysql -u root -p cpd_tracker < database/migrations/version_X.sql

# Clear any caches
rm -rf cache/*
```

## 🚧 Future Enhancements

### Planned Features
- [ ] Email notifications for goal deadlines
- [ ] Advanced analytics dashboard with charts
- [ ] Mobile app (React Native)
- [ ] API for third-party integrations
- [ ] Automated CPD reminders
- [ ] Skills tracking and competency matrices
- [ ] Integration with learning management systems
- [ ] Multi-language support
- [ ] Custom fields for CPD entries
- [ ] PDF report generation
- [ ] Calendar integration (two-way sync)
- [ ] Badge system for achievements

## 📝 License

This project is proprietary software. All rights reserved.

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Code Standards
- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Comment complex logic
- Write secure code (validate inputs, use prepared statements)
- Test thoroughly before submitting

## 📧 Support

For support, please contact:
- Email: support@cpdtracker.com
- Documentation: https://docs.cpdtracker.com
- Issues: GitHub Issues page

## 👏 Acknowledgments

- Icons and emojis for enhanced UX
- Modern gradient designs for visual appeal
- Responsive design principles
- Security best practices from OWASP

## 📊 System Requirements

### Minimum
- 512 MB RAM
- 1 GB disk space
- PHP 7.4
- MySQL 8.0

### Recommended
- 2 GB RAM
- 10 GB disk space (for file uploads)
- PHP 8.1+
- MySQL 8.0+
- SSL certificate
- CDN for static assets

---

**Version:** 1.0.0  
**Last Updated:** December 2025  
**Author:** CPD Tracker Development Team
