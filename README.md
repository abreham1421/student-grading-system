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
6. ---

## 🔐 Login Credentials

| Role    | Username     | Password    |
|---------|-------------|-------------|
| Admin   | admin        | admin123    |
| Teacher | nahil.ahmed  | teacher123  |
| Student | abebe.b      | student123  |

---

## 📁 Project Structure
student_grading_system/
├── admin/                  # Admin pages
│   ├── dashboard.php
│   ├── manage_students.php
│   ├── manage_teachers.php
│   ├── manage_subjects.php
│   ├── enroll_student.php
│   ├── view_grades.php
│   └── reports.php
├── teacher/                # Teacher pages
│   ├── dashboard.php
│   ├── enter_grades.php
│   └── class_performance.php
├── student/                # Student pages
│   ├── dashboard.php
│   ├── view_results.php
│   └── download_report.php
├── includes/               # Core files
│   ├── config.php
│   ├── db_connection.php
│   ├── auth.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── database/
│   └── grading_system.sql  # Database schema + sample data
└── index.php               # Login page
---

## 📸 Screenshots

<img width="1839" height="1203" alt="Screenshot 2026-05-05 225326" src="https://github.com/user-attachments/assets/25f3a8a0-522c-46ba-adba-427e48ffef4a" />
<img width="2234" height="1235" alt="Screenshot 2026-05-05 231104" src="https://github.com/user-attachments/assets/a5f18d86-a3ce-4fe8-ac44-ac16270960ce" />


## 👨‍💻 Developer

**Abreham Gosa**
- School: Gada Secondary School, Jimma, Ethiopia
- Email: abreham1214@gmail.com

---

## 📄 License

This project is for educational use at Gada Secondary School.
