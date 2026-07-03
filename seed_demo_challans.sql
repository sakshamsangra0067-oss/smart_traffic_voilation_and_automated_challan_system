USE traffic_system;

INSERT INTO challans (id, user_id, vehicle_no, violation, fine_amount, status) VALUES
(101, 1, 'DL01AB1234', 'Helmet', 1000, 'Unpaid'),
(102, 1, 'DL05CD7788', 'Overspeed', 2000, 'Paid'),
(103, 1, 'DL03EF4567', 'Signal Jump', 1500, 'Unpaid'),
(104, 1, 'DL09GH2201', 'Mobile Usage', 1000, 'Paid'),
(105, 1, 'HR26JK9087', 'Helmet', 1000, 'Unpaid'),
(106, 1, 'DL08LM6621', 'Overspeed', 2000, 'Unpaid'),
(107, 1, 'DL02NP3304', 'Helmet', 1000, 'Paid'),
(108, 1, 'UP16QR5521', 'Signal Jump', 1500, 'Unpaid'),
(109, 1, 'DL07ST1188', 'Mobile Usage', 1000, 'Unpaid'),
(110, 1, 'HR29UV8844', 'Helmet', 1000, 'Paid')
ON DUPLICATE KEY UPDATE
vehicle_no = VALUES(vehicle_no),
violation = VALUES(violation),
fine_amount = VALUES(fine_amount),
status = VALUES(status);
