<?php
session_start();
if (!isset($_SESSION['auth'])) {
    header('Location: index.html');
    exit;
}
$auth = $_SESSION['auth'];

include 'config.php';

$userPhotoUrl = './assets/img/me.jpg';
if ($auth['role'] === 'admin') {
    $stmt = mysqli_prepare($conn, "SELECT photo FROM tbl_admin WHERE admin_id = ? LIMIT 1");
    $idCol = $auth['id'];
    mysqli_stmt_bind_param($stmt, "i", $idCol);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!empty($row['photo'])) {
        $userPhotoUrl = './assets/img/admins/' . $row['photo'];
    }
}
?>
<!doctype html>
<html lang="en">
  <!--begin::Head-->
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>JET Montessori School of Ramon, Incorporated</title>
      <link rel="icon" type="image/png" href="logos/logo1.png"/>
    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <!--end::Accessibility Meta Tags-->

    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="adminlte/css/adminlte.css" as="style" />
    <!-- Bootstrap FIRST -->
  

    <link rel="stylesheet" href="plugins/overlayscrollbars/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="plugins/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="plugins/icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="adminlte/css/adminlte.css" />
    <link rel="stylesheet" href="plugins/datatables/datatables.min.css">
    <link rel="stylesheet" href="plugins/datatables_buttons/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="plugins/select2/select2.min.css">
    <link rel="stylesheet" href="plugins/sweetalert/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/theme.css?v=<?php echo time(); ?>">

  </head>
  <!--end::Head-->
  <!--begin::Body-->
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <!--begin::Decorative Background-->
    <div class="bg-deco" aria-hidden="true">
      <svg class="deco-1" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
        <path fill="#12a480" d="M45.2,-58.4C58.4,-49.9,68.6,-35.7,72.6,-19.8C76.6,-3.9,74.4,13.7,66.6,28.2C58.8,42.7,45.4,54.1,30.1,61.6C14.8,69,-2.4,72.6,-19.1,69.6C-35.9,66.6,-52.2,57,-62.6,42.9C-73,28.9,-77.5,10.4,-74.9,-6.4C-72.3,-23.2,-62.6,-38.4,-49.5,-47.1C-36.4,-55.8,-19.9,-58.1,-2.1,-54.7C15.6,-51.4,31.9,-66.9,45.2,-58.4Z" transform="translate(100 100)" />
      </svg>
      <svg class="deco-2" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
        <path fill="#f2b134" d="M39.6,-51.2C51.4,-42.6,61,-30.6,65.3,-16.7C69.6,-2.8,68.6,13,62,26.1C55.4,39.2,43.3,49.6,29.6,56.8C15.9,64,0.6,68,-15.4,66.3C-31.5,64.6,-48.3,57.2,-58.9,44.5C-69.5,31.7,-73.9,13.6,-72.1,-3.5C-70.3,-20.6,-62.3,-36.7,-50,-46.1C-37.7,-55.5,-21.1,-58.2,-4.3,-52.6C12.5,-47,27.8,-59.8,39.6,-51.2Z" transform="translate(100 100)" />
      </svg>
      <svg class="deco-3" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
        <path fill="#7b6ef6" d="M42.8,-56.3C54.9,-49.1,63.6,-35.8,67.8,-21C72,-6.2,71.7,10.1,65.6,23.9C59.5,37.7,47.6,49,33.9,57.3C20.2,65.6,4.7,70.9,-11.4,70.2C-27.6,69.6,-44.4,63.1,-56.4,51.2C-68.5,39.3,-75.8,22.1,-76.5,4.5C-77.2,-13.1,-71.3,-31.1,-59.8,-43.8C-48.3,-56.5,-31.2,-63.8,-14.4,-64.9C2.5,-66,30.7,-63.5,42.8,-56.3Z" transform="translate(100 100)" />
      </svg>
    </div>
    <!--end::Decorative Background-->
    <!--begin::App Wrapper-->
    <div class="app-wrapper">
      <!--begin::Header-->
      <nav class="app-header navbar navbar-expand bg-body">
        <!--begin::Container-->
        <div class="container-fluid">
          <!--begin::Start Navbar Links-->
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                <i class="bi bi-list"></i>
              </a>
            </li>

            <li class="nav-item d-none d-md-block">
              <a href="./mainpage.php" class="nav-link">
                <i class="bi bi-grid-1x2 me-1" aria-hidden="true"></i>
                Home
              </a>
            </li>
            <li class="nav-item d-none d-md-block">
              <a href="#" id="nav_school_calendar" class="nav-link">
                <i class="bi bi-calendar" aria-hidden="true"></i>
                School Calendar
              </a>
            </li>
          </ul>
          <!--end::Start Navbar Links-->

          <!--begin::End Navbar Links-->
          <ul class="navbar-nav ms-auto">
            <!--begin::Fullscreen Toggle-->
            <li class="nav-item">
              <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                <i data-lte-icon="minimize" class="bi bi-fullscreen-exit d-none"></i>
              </a>
            </li>
            <!--end::Fullscreen Toggle-->

            <!--begin::Color Mode Toggle (#6010)-->
            <li class="nav-item dropdown">
              <a
                class="nav-link"
                href="#"
                id="bd-theme"
                aria-label="Toggle color scheme"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                <i class="bi bi-sun-fill" data-lte-theme-icon="light"></i>
                <i class="bi bi-moon-fill d-none" data-lte-theme-icon="dark"></i>
                <i class="bi bi-circle-half d-none" data-lte-theme-icon="auto"></i>
              </a>
              <ul
                class="dropdown-menu dropdown-menu-end"
                aria-labelledby="bd-theme"
                style="--bs-dropdown-min-width: 8rem"
              >
                <li>
                  <button
                    type="button"
                    class="dropdown-item d-flex align-items-center"
                    data-bs-theme-value="light"
                    aria-pressed="false"
                  >
                    <i class="bi bi-sun-fill me-2"></i>
                    Light
                    <i class="bi bi-check-lg ms-auto d-none"></i>
                  </button>
                </li>
                <li>
                  <button
                    type="button"
                    class="dropdown-item d-flex align-items-center"
                    data-bs-theme-value="dark"
                    aria-pressed="false"
                  >
                    <i class="bi bi-moon-fill me-2"></i>
                    Dark
                    <i class="bi bi-check-lg ms-auto d-none"></i>
                  </button>
                </li>
                <li>
                  <button
                    type="button"
                    class="dropdown-item d-flex align-items-center active"
                    data-bs-theme-value="auto"
                    aria-pressed="true"
                  >
                    <i class="bi bi-circle-half me-2"></i>
                    Auto
                    <i class="bi bi-check-lg ms-auto d-none"></i>
                  </button>
                </li>
              </ul>
            </li>
            <!--end::Color Mode Toggle-->

            <!--begin::User Menu Dropdown-->
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img
                  src="<?php echo htmlspecialchars($userPhotoUrl); ?>"
                  class="user-image rounded-circle shadow"
                  alt="User Image"
                  id="topbarUserPhoto"
                />
                <span class="d-none d-md-inline"><?php echo htmlspecialchars($auth['name']); ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <!--begin::User Image-->
                <li class="user-header text-bg-info">
                  <img
                    src="<?php echo htmlspecialchars($userPhotoUrl); ?>"
                    class="rounded-circle shadow"
                    alt="User Image"
                    id="dropdownUserPhoto"
                  />
                  <p>
                    <?php echo htmlspecialchars($auth['name']); ?>
                    <small><?php echo htmlspecialchars(ucfirst($auth['role'])); ?></small>
                  </p>
                </li>
                <!--end::User Image-->
                <!--begin::Menu Body-->
                <li class="user-body">
                  <!--begin::Row-->
                  <div class="row">
                    <div class="col-4 text-center">
                      <a href="#" data-bs-toggle="modal" data-bs-target="#profilePhotoModal">
                       Profile
                      </a>
                    </div>
                    <div class="col-4 text-center">
                      <a href="#">
                        Calendar
                      </a>
                    </div>
                    <div class="col-4 text-center">
                      <a href="#">
                       Lock
                      </a>
                    </div>
                  </div>
                  <!--end::Row-->
                </li>
                <!--end::Menu Body-->
                <!--begin::Menu Footer-->
                <li class="user-footer">
                  <a href="#" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Change Password</a>
                  <a href="config/logout.php" class="btn btn-outline-danger float-end">Log out</a>
                </li>
                <!--end::Menu Footer-->
              </ul>
            </li>
            <!--end::User Menu Dropdown-->
          </ul>
          <!--end::End Navbar Links-->
        </div>
        <!--end::Container-->
      </nav>
      <!--end::Header-->
      <!--begin::Sidebar-->
      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="Dark">
        <!--begin::Sidebar Brand-->
        <div class="sidebar-brand">
          <!--begin::Brand Link-->
          <a href="./mainpage.php" class="brand-link">
            <!--begin::Brand Image-->
            <img
              src="logos/logo2.png"
              alt="School Logo"
              class="brand-image"
            />
            <!--end::Brand Image-->
            <!--begin::Brand Text-->
            <span class="brand-text fw-light"><b>JETMS</b> Infosys</span>
            <!--end::Brand Text-->
          </a>
          <!--end::Brand Link-->
        </div>
        <!--end::Sidebar Brand-->
        <!--begin::Sidebar Wrapper-->
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <!--begin::Sidebar Menu-->
            <ul
              class="nav sidebar-menu flex-column"
              data-lte-toggle="treeview"
              role="navigation"
              aria-label="Main navigation"
              data-accordion="false"
              id="navigation"
            >
              <?php if ($auth['role'] === 'admin'): ?>
              <li class="nav-header">ADMIN PANEL</li>
              <li class="nav-item">
                <a href="#" name = "admin_dash" id = "admin_dash" class="nav-link">
                  <i class="nav-icon bi bi-speedometer2"></i>
                  <p>Dashboard</p>
                </a>
              </li>
               <li class="nav-item">
                <a href="#" name = "admin_students" id = "admin_students" class="nav-link">
                  <i class="nav-icon bi bi-people"></i>
                  <p>Students</p>
                </a>
              </li>
               <li class="nav-item">
                <a href="#" name = "admin_enroll" id = "admin_enroll" class="nav-link">
                  <i class="nav-icon bi bi-people"></i>
                  <p>Enrollment</p>
                </a>
              </li>
              <li class="nav-item">
                    <a href="#" name = "admin_payments" id = "admin_payments" class="nav-link">
                      <i class="nav-icon bi bi-wallet2"></i>
                      <p>Payments</p>
                    </a>
              </li>
              <li class="nav-item">
                    <a href="#" name = "admin_attendance" id = "admin_attendance" class="nav-link">
                      <i class="nav-icon bi bi-geo-alt"></i>
                      <p>Attendance</p>
                    </a>
              </li>
              <li class="nav-item">
                    <a href="#" name = "admin_announcements" id = "admin_announcements" class="nav-link">
                      <i class="nav-icon bi bi-megaphone"></i>
                      <p>Announcements <span class="badge bg-danger rounded-pill announcement-badge d-none"></span></p>
                    </a>
              </li>  
              <li class="nav-item">
                    <a href="#" name = "admin_settings" id = "admin_settings" class="nav-link">
                      <i class="nav-icon bi bi-gear"></i>
                      <p>Admin Settings</p>
                    </a>
              </li>
              <?php endif; ?>

              <?php if ($auth['role'] === 'teacher'): ?>
              <li class="nav-header">TEACHER'S PANEL</li>
              <li class="nav-item">
                <a href="#" name = "teacher_classes" id = "teacher_classes" class="nav-link">
                  <i class="nav-icon bi bi-diagram-2"></i>
                  <p>My Classes</p>
                </a>
              </li>
              <li class="nav-item">
                    <a href="#" name = "teacher_announcements" id = "teacher_announcements" class="nav-link">
                      <i class="nav-icon bi bi-megaphone"></i>
                      <p>Announcements <span class="badge bg-danger rounded-pill announcement-badge d-none"></span></p>
                    </a>
              </li>
              <?php endif; ?>

              <?php if ($auth['role'] === 'student'): ?>
              <li class="nav-header">STUDENT'S PANEL</li>
              <li class="nav-item">
                <a href="#" name = "student_subjects" id = "student_subjects" class="nav-link">
                  <i class="nav-icon bi bi-diagram-2"></i>
                  <p>My subjects</p>
                </a>
              </li>
               <li class="nav-item">
                <a href="#" name = "student_grades" id = "student_grades" class="nav-link">
                  <i class="nav-icon bi bi-award"></i>
                  <p>My Grades</p>
                </a>
              </li>
              <li class="nav-item">
                    <a href="#" name = "student_payments" id = "student_payments" class="nav-link">
                      <i class="nav-icon bi bi-wallet2"></i>
                      <p>Payment History</p>
                    </a>
              </li>
              <li class="nav-item">
                    <a href="#" name = "student_announcements" id = "student_announcements" class="nav-link">
                      <i class="nav-icon bi bi-megaphone"></i>
                      <p>Announcements <span class="badge bg-danger rounded-pill announcement-badge d-none"></span></p>
                    </a>
              </li>
              <?php endif; ?>

            </ul>
            <!--end::Sidebar Menu-->

            <!-- Docs CTA (bottom of sidebar) -->
            <div class="p-3 mt-3 border-top border-secondary border-opacity-25">
              <a
                href="./docs/introduction.html"
                target="_blank"
                rel="noopener"
                class="btn btn-sm btn-outline-light w-100 d-flex align-items-center justify-content-center gap-2"
              >
              <i class="bi bi-book" aria-hidden="true"></i>
                Documentation
              </a>
              <small class="text-muted d-block mt-2 text-center" style="font-size: 0.75rem;">
                  Version 1.0
              </small>
            </div>
          </nav>
        </div>
        <!--end::Sidebar Wrapper-->
      </aside>
      <!--end::Sidebar-->

      <!--begin::App Main-->
      <main class="app-main" name = "app-main" id = "app-main">
        <div id="content_level"></div>
      </main>
      <!--end::App Main-->

      <!--begin::Footer-->
      <footer class="app-footer">
        <!--begin::To the end-->
        <div class="float-end d-none d-sm-inline">Tel/Phone: 078 258 5796 / 0935 819 5028<br> Email: jetmontessorischool@gmail.com</div>
        <!--end::To the end-->
        <!--begin::Copyright-->
        <strong>
          JET Montessori School of Ramon, Incorporated&nbsp;<br>
          <!---a href="#" class="text-decoration-none">078 258 5796 / 0935 819 5028</a--->
        </strong>
        Ramon, Isabela, 3319 Philippines
        <!--end::Copyright-->
      </footer>
      <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->

    <!--begin::Change Password Modal-->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Change Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="changePasswordForm" autocomplete="off">
              <div class="mb-3">
                <label class="form-label" for="current_password">Current Password</label>
                <div class="input-group">
                  <input type="password" class="form-control" id="current_password" name="current_password" required>
                  <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="new_password">New Password</label>
                <div class="input-group">
                  <input type="password" class="form-control" id="new_password" name="new_password" required minlength="4">
                  <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <div class="input-group">
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="4">
                  <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="changePasswordForm" class="btn btn-primary">Change Password</button>
          </div>
        </div>
      </div>
    </div>
    <!--end::Change Password Modal-->

    <!--begin::Profile Photo Modal-->
    <div class="modal fade" id="profilePhotoModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Profile Photo</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body text-center">
            <img id="profilePhotoPreview" src="<?php echo htmlspecialchars($userPhotoUrl); ?>" class="rounded-circle shadow mb-3" style="width:140px;height:140px;object-fit:cover;">
            <form id="profilePhotoForm">
              <input type="file" class="form-control" id="profile_photo_input" accept="image/png,image/jpeg,image/webp" required>
            </form>
          </div>
          <div class="modal-footer">
            <button type="submit" form="profilePhotoForm" class="btn btn-primary">Upload</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
    <!--end::Profile Photo Modal-->

    <script src="jquery/jquery.min.js"></script>
    <script src="plugins/overlayscrollbars/overlayscrollbars.browser.es6.min.js"></script>
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="adminlte/js/adminlte.js"></script>
    <script src="plugins/datatables/datatables.min.js"></script>
    <script src="plugins/datatables_buttons/dataTables.buttons.min.js"></script>
    <script src="plugins/datatables_buttons/buttons.html5.min.js"></script>
    <script src="plugins/datatables_buttons/buttons.print.min.js"></script>
    <script src="plugins/datatables_buttons/jszip.js"></script>
    <script src="plugins/datatables_buttons/pdfmake.js"></script>
    <script src="plugins/datatables_buttons/vfs_fonts.js"></script>
    <script src="plugins/select2/select2.full.min.js"></script>
    <script src="plugins/sweetalert/sweetalert2.min.js"></script>
    <script>
      window.__jetms_role = <?php echo json_encode($auth['role']); ?>;
    </script>
    <script src="jsfile/main.js?v=<?php echo time(); ?>"></script>
    <script src="jsfile/navi.js?v=<?php echo time(); ?>"></script>
    <script src="jsfile/change_password.js?v=<?php echo time(); ?>"></script>
    <script src="jsfile/profile_photo.js?v=<?php echo time(); ?>"></script>
    
  </body> 
  <!--end::Body-->
</html>
