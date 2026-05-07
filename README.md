# 🎓 Student Grading System
### Gada Secondary School — Jimma, Ethiopia

A complete web-based student grading and academic management system built with PHP and MySQL.

---

## 📋 Features

- 👨‍💼 **Admin Panel** — Manage students, teachers, subjects, enrollments
- 👩‍🏫 **Teacher Panel** — Enter grades, view class performance
- 👨‍🎓 **Student Panel** — View results, download report card
- 📊 **Reports & Analytics** — Grade statistics, GPA calculation
- 🔐 **Secure Login** — Role-based access (Admin / Teacher / Student)
- 📱 **Responsive Design** — Works on mobile and desktop

---

## 🛠️ Built With

- **Backend:** PHP 8.x
- **Database:** MySQL
- **Frontend:** Bootstrap 5, HTML, CSS, JavaScript
- **Charts:** Chart.js
- **Icons:** Font Awesome 6

---

## ⚙️ Installation

### Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache/Nginx (XAMPP recommended for local)

### Steps

1. **Clone the repository**
```bash
git clone https://github.com/abreham1421/student-grading-system.git
```

2. **Move to your web server folder**
```bash
# For XAMPP
mv student-grading-system C:/xampp/htdocs/
```

3. **Import the database**
   - Open **phpMyAdmin** → `http://localhost/phpmyadmin`
   - Click **Import**
   - Select the file: `database/grading_system.sql`
   - Click **Go**

4. **Configure database connection**
   - Open `includes/config.php`
   - Update your database settings:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gada_grading_system');
```

5. **Open in browser**
```
http://localhost/student_grading_system/
```

---

## 🔐 Login Credentials

| Role    | Username     | Password    |
|---------|-------------|-------------|
| Admin   | admin        | admin123    |
| Teacher | nahil.ahmed  | teacher123  |
| Student | abebe.b      | student123  |

---

## 📁 Project Structure

```
student_grading_system/
│
├── index.php                        # Login page (entry point)
├── authenticate.php                 # Handles login form POST & session creation
├── logout.php                       # Destroys session and redirects to login
├── reset_password.php               # Admin utility to reset any user password
├── create_tables.php                # Utility: creates missing DB tables if needed
├── test_login.php                   # Development: test login without browser
├── README.md                        # This file
│
├── admin/                           # Admin role pages
│   ├── dashboard.php                # Admin home with stats & charts
│   ├── manage_students.php          # List, add, delete students
│   ├── edit_student.php             # Edit student info & reset password
│   ├── view_student.php             # Full student profile & grade history
│   ├── delete_student.php           # Confirm and delete student
│   ├── enroll_student.php           # Enroll student into subjects
│   ├── manage_teachers.php          # List, add, delete teachers
│   ├── edit_teacher.php             # Edit teacher info & assign subjects
│   ├── view_teacher.php             # Full teacher profile & grades entered
│   ├── manage_subjects.php          # List, add, delete subjects
│   ├── edit_subject.php             # Edit subject & assign teacher
│   ├── view_subject.php             # Subject stats, enrolled students
│   ├── view_grades.php              # View all grades with filters
│   ├── reports.php                  # Reports and analytics
│   ├── audit_log.php                # System activity log
│   └── profile.php                  # Admin profile & password
│
├── teacher/                         # Teacher role pages
│   ├── dashboard.php                # Teacher home with subject overview
│   ├── enter_grades.php             # Enter / update student grades
│   ├── my_students.php              # View students in assigned subjects
│   ├── class_performance.php        # Class statistics & charts
│   ├── view_teacher.php             # Teacher's own profile view
│   └── profile.php                  # Edit profile & change password
│
├── student/                         # Student role pages
│   ├── dashboard.php                # Student home with grade summary
│   ├── view_results.php             # View all grades by subject
│   ├── download_report.php          # Download printable report card
│   ├── profile.php                  # View & edit profile
│   └── change_password.php          # Change own password
│
├── includes/                        # Core shared PHP files
│   ├── config.php                   # App settings, DB constants, session start
│   ├── db_connection.php            # MySQL connection (singleton class)
│   ├── auth.php                     # Login, logout, role checking (Auth class)
│   ├── functions.php                # Helper functions (grades, GPA, etc.)
│   ├── header.php                   # Navigation bar + sidebar (every page)
│   └── footer.php                   # Footer + JS scripts (every page)
│
├── assets/                          # Static frontend files
│   ├── css/
│   │   ├── style.css                # Main stylesheet (sidebar, cards, layout)
│   │   ├── login.css                # Login page specific styles
│   │   └── responsive.css           # Mobile responsive overrides
│   └── js/
│       └── main.js                  # Global JavaScript (alerts, confirmations)
│
├── database/                        # Database files
│   └── grading_system.sql           # Full schema + sample data (import this)
│
└── uploads/                         # Profile picture uploads (auto-created)
```

---

## 📸 Screenshots

<img width="1839" height="1203" alt="Screenshot 2026-05-05 225326" src="https://github.com/user-attachments/assets/25f3a8a0-522c-46ba-adba-427e48ffef4a" />
<img width="2239" height="1245" alt="Screenshot 2026-05-05 231307" src="https://github.com/user-attachments/assets/2eefd47a-62b9-4478-a6b8-a7a3c9911b0a" />
<img width="2239" height="1242" alt="Screenshot 2026-05-05 225932" src="https://github.com/user-attachments/assets/99086444-95d0-4091-bb73-72aec5100831" />
<img width="2234" height="1235" alt="Screenshot 2026-05-05 231104" src="https://github.com/user-attachments/assets/a5f18d86-a3ce-4fe8-ac44-ac16270960ce" />

---

## 👨‍💻 Developer

**Abreham Gosa**
- School: Gada Secondary School, Jimma, Ethiopia
- Email: abreham1214@gmail.com

---

## 📄 License

This project is for educational use at Gada Secondary School.
