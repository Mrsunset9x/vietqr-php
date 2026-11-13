# Phát hành lên Packagist

GitHub lưu mã nguồn và tag phiên bản. Packagist lập chỉ mục repo để người dùng có thể
cài package bằng Composer mà không cần tự khai báo repository.

Tên package là `mrsunset9x/vietqr-php`. Repo nguồn:
`https://github.com/Mrsunset9x/vietqr-php.git`.

## Trạng thái phát hành

Package đã được đăng ký tại
[Packagist](https://packagist.org/packages/mrsunset9x/vietqr-php), không cần Submit lại.
Bản `v0.1.0` đã được kiểm tra ngày 13/11/2026: cài trực tiếp vào ứng dụng mới,
không khai báo repository riêng, nhận đúng commit của tag và chạy được ví dụ README.
Phần lõi không kéo thêm package runtime.

Để kiểm tra một bản phát hành, chạy trong thư mục ứng dụng mới:

```sh
composer require "mrsunset9x/vietqr-php:~0.1.0"
composer show mrsunset9x/vietqr-php
```

README đã dùng Packagist làm cách cài chính. Người duy trì có thể kiểm tra đồng bộ
GitHub theo [hướng dẫn chính thức](https://packagist.org/about#how-to-update-packages)
để Packagist nhận các tag mới khi push.

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
