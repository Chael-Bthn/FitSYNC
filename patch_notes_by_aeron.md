**Database / Schema**

* Expanded database/fitsync.sql (line 1\) with richer operational tables:  
  * membership\_plans, memberships  
  * attendance\_logs  
  * classes, class\_schedules, class\_bookings  
  * branch\_announcements, branch\_operating\_hours  
  * member\_notes  
  * login/account support data  
* Added seed data for branches, plans, classes, schedules, announcements, hours, users, and memberships.  
* Added indexes and foreign keys for the new membership, attendance, schedule, booking, and notes workflows.

**Authentication / Role Flow**

* Added or strengthened role protection through config/auth\_guard.php (line 1).  
* Updated auth handling in handlers/auth\_handler.php (line 1), including admin/member redirects and login logging.  
* Admins route to admin.php; members route to profile.php.

**Member Profile Became the Member Hub**

* Consolidated the previous member hub/profile split into profile.php (line 1).  
* Added tabbed member sections:  
  * Dashboard / today panel  
  * Programs  
  * Billing / membership renewal  
  * Schedule / class bookings  
  * Feedback  
  * Settings  
* Broke member UI into includes:  
  * member\_today\_panel.php (line 1\)  
  * member\_membership\_section.php (line 1\)  
  * member\_attendance\_section.php (line 1\)  
  * member\_schedule\_section.php (line 1\)

**Member Features**

* Added active membership detection, renewal flow, and membership history helpers.  
* Added attendance tracking, total visits, streak calculation, monthly visits, and “today” status.  
* Added member class schedule browsing, booking, and cancellation.  
* Added branch announcements and operating hours into the member experience.  
* Added profile update, password change, feedback submission, attendance logging, membership renewal, class booking, and booking cancellation actions in handlers/profile\_handler.php (line 1).

**Admin Dashboard Expanded**

* admin.php (line 1\) became the central admin panel with:  
  * Dashboard analytics  
  * Member management  
  * Branch overview  
  * Schedule management  
  * Announcement management  
  * Feedback review  
  * Reports  
  * Settings  
* Added membership operations: approve/reject payments, freeze/reactivate/cancel memberships, and view pending/expired/expiring memberships.  
* Added attendance analytics: today’s check-ins, branch attendance, inactive members, most active members.  
* Added reports with date filters, revenue analytics, member analytics, attendance analytics, and CSV export via handlers/report\_export.php (line 1).

**Admin Schedule / Announcement Integration**

* Integrated the separate admin schedule and announcement pages into the main admin.php.  
* Old pages now redirect:  
  * admin/schedules.php (line 1\) \-\> admin.php?page=schedules  
  * admin/announcements.php (line 1\) \-\> admin.php?page=announcements  
* Updated admin sidebar links to point back into the main admin panel.

**Admin Member Detail Page**

* Added admin/member\_view.php (line 1\) for viewing a single member’s profile, membership lifecycle, attendance insights, retention indicators, internal notes, and timeline.  
* Added admin actions for extending memberships, changing branch, and adding private notes.

**Backend Helper Layer**

* Added reusable helper files:  
  * membership\_helpers.php (line 1\)  
  * attendance\_helpers.php (line 1\)  
  * schedule\_helpers.php (line 1\)  
  * member\_helpers.php (line 1\)  
  * member\_dashboard\_helpers.php (line 1\)  
  * report\_helpers.php (line 1\)

**Admin Action API**

* Added handlers/admin\_handler.php (line 1\) for admin-side JSON actions:  
  * Payment approval/rejection  
  * Membership status updates  
  * Membership extension  
  * Member branch changes  
  * Member notes  
  * Class CRUD  
  * Class schedule CRUD/status  
  * Announcement CRUD/status  
  * Operating hours updates  
  * 

