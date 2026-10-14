import os

f = 'app/lib/bootstrap.php'
with open(f, 'r', encoding='utf-8') as file:
    c = file.read()

search = """        // 3. Reports & QR_Label Accordion"""

replace = """        // 2. Maintenance Aset Accordion
        $maintRoutes = ['maintenance', 'tickets', 'walkarounds', 'jobs', 'corrective_job_desks', 'mobile_service'];
        $isMaintOpen = in_array($curRoute, $maintRoutes, true);
        if ($isAdmin || has_regulation('maintenance', 'view', $user) || has_regulation('tickets', 'view', $user) || has_regulation('walkarounds', 'view', $user) || has_regulation('jobs', 'view', $user) || has_regulation('corrective_job_desks', 'view', $user) || has_regulation('mobile_service', 'view', $user)) {
            echo '<details class="nav-accordion"' . ($isMaintOpen ? ' open' : '') . '>';
            echo '  <summary class="nav-item" title="Maintenance Aset"><span class="nav-icon">' . $svgTool . '</span><span class="nav-label">Maintenance Aset</span><span class="nav-chevron">▼</span></summary>';
            echo '  <div class="nav-submenu">';
            if ($isAdmin || has_regulation('maintenance', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'maintenance' ? ' active' : '') . '" href="' . route_url('maintenance') . '"><span class="nav-icon">' . $svgTool . '</span><span class="nav-label">Schedule PM</span></a>';
            }
            if ($isAdmin || has_regulation('tickets', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'tickets' ? ' active' : '') . '" href="' . route_url('tickets') . '"><span class="nav-icon">' . $svgTicket . '</span><span class="nav-label">Tiket Corrective</span></a>';
            }
            if ($isAdmin || has_regulation('walkarounds', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'walkarounds' ? ' active' : '') . '" href="' . route_url('walkarounds') . '"><span class="nav-icon">' . $svgPatrol . '</span><span class="nav-label">Patroli Walkaround</span></a>';
            }
            if ($isAdmin || has_regulation('jobs', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'jobs' ? ' active' : '') . '" href="' . route_url('jobs') . '"><span class="nav-icon">' . $svgLayers . '</span><span class="nav-label">Job Desk PM</span></a>';
            }
            if ($isAdmin || has_regulation('corrective_job_desks', 'view', $user)) {
                echo '<a class="nav-item' . ($curRoute === 'corrective_job_desks' ? ' active' : '') . '" href="' . route_url('corrective_job_desks') . '"><span class="nav-icon">' . $svgLayers . '</span><span class="nav-label">Job Desk Corrective</span></a>';
            }
            if ($isAdmin || has_regulation('mobile_service', 'view', $user)) {
                echo '<a class="nav-item" href="' . route_url('mobile_service') . '" target="_blank"><span class="nav-icon">' . $svgMobile . '</span><span class="nav-label">📱 Mobile Service</span><span class="nav-badge">PWA</span></a>';
            }
            echo '  </div>';
            echo '</details>';
        }

        // 3. Reports & QR_Label Accordion"""

if search in c:
    c = c.replace(search, replace)
    with open(f, 'w', encoding='utf-8') as file:
        file.write(c)
    print("Restored Maintenance Aset accordion in bootstrap.php successfully")
else:
    print("Search target not found")

