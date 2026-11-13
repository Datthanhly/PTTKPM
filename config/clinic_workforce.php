<?php

return [
    // Fictional accounts for the academic demo; these are not real practitioners.
    'doctors_per_specialty' => 3, // Legacy initial seeder; clinic:coverage uses the per-branch target below.
    'doctors_per_specialty_per_branch' => 2,
    'new_doctor_hourly_rate' => 500000,
    'staff_per_role_per_branch' => 2,
    'schedule_days' => 14,
    'doctor_names' => [
        'TM' => ['Nguyễn Minh An', 'Phạm Quang Huy', 'Đỗ Thanh Tùng', 'Lê Tuấn Anh', 'Trần Đức Hải', 'Nguyễn Hữu Sơn'],
        'NOI' => ['Trần Thu Hà', 'Nguyễn Thị Mai', 'Lê Văn Hùng', 'Phạm Thị Hạnh', 'Đỗ Quốc Việt', 'Vũ Minh Đức'],
        'NHI' => ['Lê Hoàng Nam', 'Trần Bảo Ngọc', 'Phạm Minh Khang', 'Nguyễn Ngọc Diệp', 'Lê Thị Quỳnh', 'Trần Minh Nhật'],
        'NGOAI' => ['Vũ Đức Long', 'Nguyễn Quốc Bảo', 'Trần Hoàng Phúc', 'Phạm Tiến Dũng', 'Đặng Văn Hòa', 'Lê Anh Kiệt'],
        'SAN' => ['Phạm Thu Trang', 'Lê Ngọc Ánh', 'Nguyễn Hoài An', 'Trần Ngọc Hương', 'Vũ Thị Yến', 'Đỗ Thùy Linh'],
        'TMH' => ['Đỗ Minh Tuấn', 'Trần Hải Yến', 'Nguyễn Thành Đạt', 'Phạm Hoàng Sơn', 'Lê Thanh Vân', 'Vũ Anh Thư'],
        'RHM' => ['Lê Anh Dũng', 'Vũ Thanh Thảo', 'Phạm Bảo Châu', 'Nguyễn Đức Huy', 'Trần Tuệ Minh', 'Đỗ Ngọc Trâm'],
        'DALIEU' => ['Nguyễn Khánh Linh', 'Trần Thùy Dương', 'Đỗ Minh Châu', 'Lê Hồng Ngọc', 'Vũ Thanh Hà', 'Phạm Tuấn Tú'],
        'MAT' => ['Phạm Đức Anh', 'Nguyễn Bích Ngọc', 'Lê Quang Minh', 'Trần Gia Bảo', 'Đỗ Thanh Mai', 'Vũ Ngọc Hân'],
        'TAMTHAN' => ['Trần Minh Đức', 'Vũ Hoàng Yến', 'Nguyễn Tuấn Kiệt', 'Lê Quốc Khánh', 'Phạm Hải Anh', 'Đỗ Thị Nhung'],
        'TAMLY' => ['Lê Phương Anh', 'Phạm Gia Hân', 'Đỗ Thảo Nhi', 'Nguyễn Thanh Tâm', 'Trần Bảo Hà', 'Vũ Minh Châu'],
    ],
    'staff_names' => [
        'receptionist' => ['Nguyễn Thị Linh', 'Trần Hồng Nhung', 'Phạm Ngọc Lan', 'Lê Minh Thư', 'Đỗ Thanh Hương', 'Vũ Bảo Trâm'],
        'cashier' => ['Trần Anh Tuấn', 'Lê Thu Hằng', 'Nguyễn Minh Tâm', 'Phạm Quỳnh Hoa', 'Vũ Thị Hạnh', 'Đỗ Đức Thành'],
    ],
];
