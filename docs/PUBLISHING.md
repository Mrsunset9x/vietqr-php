# Phát hành lên Packagist

GitHub lưu mã nguồn và tag phiên bản. Packagist lập chỉ mục repo để người dùng có thể
cài package bằng Composer mà không cần tự khai báo repository.

Tên package là `mrsunset9x/vietqr-php`. Repo nguồn:
`https://github.com/Mrsunset9x/vietqr-php.git`.

## Đăng ký lần đầu

1. Đăng nhập tài khoản quản lý package tại [Packagist](https://packagist.org/login/).
2. Mở [Submit](https://packagist.org/packages/submit).
3. Nhập URL repo GitHub ở trên, kiểm tra tên package rồi gửi.
4. Chờ Packagist đọc `composer.json` và các tag. Kiểm tra trang package có bản `v0.1.0`.
5. Bật đồng bộ GitHub theo [hướng dẫn chính thức](https://packagist.org/about#how-to-update-packages)
   để Packagist nhận các tag mới khi push. Chỉ cấp quyền cần thiết cho repo này.

Khi package đã xuất hiện trên Packagist, kiểm tra bằng một thư mục ứng dụng mới,
không khai báo repository GitHub hoặc path repository:

```sh
composer require "mrsunset9x/vietqr-php:~0.1.0"
composer show mrsunset9x/vietqr-php
```

Sau khi cài thành công, cập nhật README để lấy lệnh Packagist làm cách cài chính.
Hướng dẫn cài qua GitHub vẫn có thể giữ trong tài liệu phát triển.

## Các bản tiếp theo

- Cập nhật changelog và kiểm tra `composer validate --strict`, `composer check`,
  `composer audit` trước khi tạo tag.
- Dùng Semantic Versioning. Nhánh `0.x` chưa cam kết API ổn định như bản `1.0`.
- Không thêm trường `version` vào `composer.json`; Composer đọc phiên bản từ tag Git.
- Tạo tag mới theo dạng `vX.Y.Z`, push tag và viết ghi chú phát hành trên GitHub.
- Đợi Packagist cập nhật rồi kiểm tra phiên bản và commit nguồn. Nếu chưa có webhook,
  dùng nút Update trên trang package khi đã đăng nhập với quyền maintainer.
- Không di chuyển hoặc ghi đè tag đã phát hành. Nếu cần sửa, tạo phiên bản tiếp theo.

GitHub Actions, GitHub Release và Packagist là ba phần riêng biệt. Có release trên
GitHub không đồng nghĩa với package đã có trên Packagist. Không cần gửi API token vào
chat hoặc đưa token vào repo để làm các bước đăng ký qua giao diện.
