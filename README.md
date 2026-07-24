# CBIT/CSSH Class Monitoring System - PHP + MySQL

This is a lightweight local PHP + MySQL class monitoring system for XAMPP/Laragon.

## Main Features

- Login system with roles/accounts:
  - `admin` / `admin123` — Admin account, can view CBIT and CSSH.
  - `cbit` / `cbit123` — CBIT account, yellow report header.
  - `cssh` / `cssh123` — CSSH account, purple report header.
- Upload Excel/CSV schedule files and automatically generate monitoring records.
- Same monitoring-table format as the Excel template:
  - INSTRUCTORS
  - SECTION
  - Subject
  - Day
  - CRONASIA PMVGO
  - Class Material
  - ACTIVITY / QUIZZES
  - Total Classes (Week)
  - Present
  - Absent
  - Remarks
- CRONASIA PMVGO has only one checkbox.
- Class Material and Activity/Quizzes have W1, W2, W3, and W4 checkboxes.
- Present/Absent are calculated from the checked Class Material and Activity/Quizzes week checkboxes.
- Remarks can be manually edited. If left blank, the system uses automatic Complete/Incomplete.
- Search by instructor name, section, subject code, course, or remarks.
- Section-year checkbox filter:
  - F1, F2, etc. = 1st Year
  - S1, S2, etc. = 2nd Year
  - T1, T2, etc. = 3rd Year
  - G1, G2, etc. = 4th Year
- Print / Save as PDF with fitted logos and header colors.
- Export to Excel-compatible `.xls` with the same report format and colored header.


## This Revised Package

This full ZIP already includes the bigger print/PDF font update. The Present and Absent columns are still computed from the checked Class Material and Activity/Quizzes boxes. The Remarks field remains editable.

## Installation in XAMPP

1. Copy the `cbit_monitoring` folder to:

   `C:\xampp\htdocs\`

2. Start Apache and MySQL in XAMPP.

3. Open:

   `http://localhost/cbit_monitoring/setup.php`

4. Click **Create / Update Database**.

5. Open:

   `http://localhost/cbit_monitoring/`

6. Login using one of the default accounts above.

## Important Notes

- Run `setup.php` after replacing your old folder. This creates/updates the CBIT and CSSH accounts and resets their default passwords.
- Supported file upload formats: `.xlsx`, `.xlsm`, `.csv`, and `.xls` if Composer/PhpSpreadsheet is installed.
- For old `.xls`, the safest option is to open it in Excel and save it as `.xlsx` before uploading.
- When printing or saving as PDF in Chrome, keep **Background graphics** enabled if your browser shows that option. The system already includes print CSS to preserve colors.


## v10 Update
- Present and Absent columns are now editable number fields.
- Checkbox changes still auto-compute Present/Absent unless you manually edit the number fields.
- Manual Present/Absent values are saved to MySQL and used in print/export.
