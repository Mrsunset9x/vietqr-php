# VietQR PHP

[![CI](https://github.com/Mrsunset9x/vietqr-php/actions/workflows/ci.yml/badge.svg)](https://github.com/Mrsunset9x/vietqr-php/actions/workflows/ci.yml)

Tạo và đọc mã thanh toán VietQR NAPAS247 ngay trong ứng dụng PHP. Phần lõi không cần
package Composer khác và không gọi API bên ngoài. Khi cần ảnh SVG hoặc PNG, bạn cài
thêm bộ mã hóa QR.

Package dùng **PHP 8.2+**, hỗ trợ chuyển tới tài khoản/thẻ, QR tĩnh/động và số tiền VND
nguyên. Có thể dùng trong PHP thuần hoặc Laravel mà không cần cấu hình riêng.

## Cài đặt

Package chưa đăng trên Packagist. Chạy hai lệnh sau trong thư mục ứng dụng để cài
trực tiếp từ GitHub:

```sh
composer config repositories.vietqr vcs https://github.com/Mrsunset9x/vietqr-php.git
composer require "mrsunset9x/vietqr-php:~0.1.0"
```

Lệnh trên chọn bản phát hành trong nhánh phiên bản `0.1.x`. Giữ `composer.lock` của
ứng dụng trong Git để các môi trường cài cùng một commit. Dependency phục vụ phát
triển package, như PHPUnit và PHPStan, không được cài vào ứng dụng tiêu thụ.

Nếu đang sửa package cùng ứng dụng, xem cách dùng source local trong
[hướng dẫn sử dụng](docs/USAGE.md).

## Tạo chuỗi VietQR

Lưu đoạn sau thành `payment.php` trong ứng dụng rồi chạy `php payment.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use VietQr\Payment;
use VietQr\VietQr;

$payment = new Payment(
    bankBin: '970403',
    accountNo: '0011012345678', // Ví dụ trong tài liệu; thay bằng tài khoản thực tế.
    amount: 180000,
    description: 'thanh toán đơn hàng',
    billNumber: 'NPS6869',
);

$payload = VietQr::generate($payment);

echo $payload, PHP_EOL;
```

`Payment` là dữ liệu bất biến, kiểm tra đầu vào ngay khi tạo. Không sửa được số tài
khoản/số tiền sau đó. Dùng `null` cho `amount` để mặc định tạo QR tĩnh; có tiền thì mặc
định QR động. Có thể chỉ định `type: QrType::Static` hoặc `QrType::Dynamic` khi cần.

```php
use VietQr\QrType;
use VietQr\Service;

$static = new Payment('970403', '0011012345678');
$card = new Payment('970403', '9704031101234567', 180000, service: Service::Card);
$reusable = new Payment('970403', '0011012345678', 180000, type: QrType::Static);
```

QR tĩnh/động mô tả phương thức khởi tạo. Package không thực thi hết hạn, chống sử dụng
lại, khóa số tiền trong app ngân hàng hoặc xác nhận đã nhận tiền.

## Xuất SVG/PNG

Chỉ cài thêm bộ mã hóa QR nếu ứng dụng chưa có giải pháp tương đương:

```sh
composer require "bacon/bacon-qr-code:~3.0"
```

Tính năng này thêm **2 package runtime**: `bacon/bacon-qr-code` và dependency
`dasprid/enum`. SVG cần `ext-xmlwriter`, PNG cần `ext-gd`; Bacon yêu cầu `ext-iconv`.

```php
use VietQr\Rendering\BaconQrRenderer;

$renderer = new BaconQrRenderer();
$svg = $renderer->svg($payment);      // Mặc định 320 x 320.
$png = $renderer->png($payment, 512); // Chuỗi byte PNG.

// Ứng dụng quyết định nơi lưu, HTTP headers và cache.
file_put_contents('payment.svg', $svg);
```

Ảnh vuông, đen/trắng, không logo che mã; vùng trắng bảo vệ 4 ô QR và sửa lỗi mức M.
Kích thước cho phép 256-2.048 px. Nên dùng SVG khi in hoặc thay đổi kích thước.
Renderer trả dữ liệu trong bộ nhớ, không tự ghi file. Thiếu package/extension sẽ báo
`LogicException` kèm cách khắc phục. Kích thước không hợp lệ báo `InvalidArgumentException`.

Nếu ứng dụng đã có bộ mã hóa QR, đưa `$payload` vào bộ mã hóa đó hoặc implement
`VietQr\Rendering\QrRenderer`. Không cần cài Bacon.

## Đọc và kiểm tra chuỗi

```php
use VietQr\InvalidVietQr;

try {
    $result = VietQr::parse($payload);
    $result->payment->accountNo; // string: giữ số 0 đầu.
    $result->payment->amount;    // ?string: không mất chính xác vì số thực.
    $result->payment->reference;
    $result->payload;            // Chuỗi gốc, giữ nguyên mọi byte.
    $result->tags;               // Các trường gốc, bao gồm trường chưa biết.
} catch (InvalidVietQr $error) {
    // Đầu vào sai, CRC sai hoặc ngoài phạm vi hỗ trợ.
}
```

Parser luôn kiểm CRC, cấu trúc TLV, trường trùng ở các tầng đã đọc, định danh NAPAS,
ngân hàng/tài khoản, phương thức, dịch vụ, VND/VN và các trường ứng dụng tiêu thụ.
Không có chế độ bỏ qua CRC. CRC chỉ phát hiện lỗi dữ liệu; ai cũng có thể tính lại
CRC, nên nó **không xác thực người gửi, chủ tài khoản hoặc việc chuyển tiền**.

Trường chưa biết được giữ trong `tags` nhưng không được diễn giải hay chứng nhận
hợp lệ về nghiệp vụ. Muốn chuyển tiếp nguyên mã, dùng `result->payload`.
Gọi `generate(result->payment)` chỉ tạo lại các trường package hỗ trợ và chuẩn hóa chúng.
Đọc chuỗi QR không đồng nghĩa với đọc QR từ ảnh.

## Quy tắc đầu vào

| Trường | Quy tắc |
|---|---|
| `bankBin` | Chuỗi đúng 6 chữ số ASCII; không bị giới hạn bởi danh mục ngân hàng |
| `accountNo` | Chuỗi 1-19 chữ/số ASCII; thẻ chỉ nhận chữ số; không tự trim hay bỏ số 0 đầu |
| `amount` | `int`, chuỗi chữ số hoặc `null`; dương, tối đa 13 chữ số; từ chối float/bool, dấu phân cách, số mũ |
| `description` | Nội dung chuyển khoản, tối đa 25 ký tự sau chuẩn hóa |
| `billNumber` | Số hóa đơn, tối đa 25 ký tự sau chuẩn hóa |
| `reference` | Mã tham chiếu, tối đa 25 ký tự sau chuẩn hóa |
| Văn bản tùy chọn | Tiếng Việt được bỏ dấu, trim khoảng trắng đầu/cuối; chuỗi rỗng thành `null`; tối đa 1.024 byte đầu vào |
| Chuỗi để parse | ASCII in được, tối đa 1.024 byte; từ chối Unicode/emoji và ký tự điều khiển |

Không cắt ngắn dữ liệu vượt giới hạn. Mã lỗi dùng `InvalidVietQr`, kế thừa
`InvalidArgumentException`. Kiểu số tiền được kiểm tra cả khi caller không bật
`strict_types`. Số nguyên quá lớn trên PHP 32-bit cần truyền dưới dạng chuỗi.

Parser chấp nhận dạng tiền nguyên có dấu chấm cuối (`50000.`) theo tài liệu NAPAS,
chuẩn hóa thành `'50000'`. API tạo mới chỉ nhận số nguyên/chuỗi chữ số. Không hỗ trợ
tiền lẻ, tiền tệ khác VND, trường ngôn ngữ Unicode hay tài khoản định danh trên 19 ký tự.
Xem [phạm vi giao thức và khác biệt với upstream](docs/PROTOCOL.md).

## Danh mục ngân hàng

```php
use VietQr\BankCatalog;

$bank = BankCatalog::find('VCB'); // Cũng nhận '970436', 'Vietcombank', không phân biệt hoa/thường.
$bank?->bin;
$bank?->name;
$bank?->shortName;
$banks = BankCatalog::all();
```

Danh mục gồm 76 ngân hàng, chỉ được nạp khi dùng tới và được giữ lại trong bộ nhớ của
PHP process. Tạo/đọc QR không cần nạp danh mục. Đây là dữ liệu tại một thời điểm,
không phải dịch vụ kiểm tra tài khoản hoặc trạng thái hoạt động của ngân hàng.
Nguồn và ngày lấy nằm trong [NOTICE.md](NOTICE.md).

## Laravel

Laravel có thể dùng thẳng API trên, không cần provider, facade Laravel, migration
hoặc publish config. Ví dụ trong controller, sau khi ứng dụng đã xác thực quyền và dữ liệu:

```php
return response((new BaconQrRenderer())->svg($payment), 200, [
    'Content-Type' => 'image/svg+xml',
]);
```

Xem [hướng dẫn sử dụng](docs/USAGE.md) để ghép vào controller và xử lý lỗi thường gặp.

## Phát triển và kiểm thử

Dùng source checkout cho các lệnh bên dưới; ZIP runtime không kèm `tests` và `tools`.

```sh
git clone https://github.com/Mrsunset9x/vietqr-php.git
cd vietqr-php
composer install
composer check
php -n tools/core-smoke.php
composer example
composer benchmark
php tools/verify-install.php
php tools/verify-install.php --with-renderer
```

`composer check` chạy lint, PHPStan mức cao nhất và PHPUnit. `php -n` chứng minh lõi
không cần intl, mbstring, GD, iconv hay XMLWriter. Các kiểm tra install tạo ứng dụng
tiêu thụ mới từ bản ZIP đã loại dependency/cache trong thư mục tạm và giữ lại để kiểm
tra. Không thay đổi global Composer. Công cụ kiểm tra ZIP cần `ext-zip` trên máy phát triển.

Kiểm tra ảnh độc lập là tùy chọn dành cho người phát triển, không thuộc dependency PHP:

```sh
php tools/render-fixtures.php
python -m pip install zxing-cpp==3.1.1 Pillow==12.3.0 PyMuPDF==1.28.0
python tools/decode-fixtures.py
```

Nên chạy Python trong virtualenv. CI cấu hình PHP 8.2-8.5 trên Linux/Windows, dependency
tối thiểu, cài ứng dụng mới và đọc ngược ảnh. Kết quả kiểm thử local nằm trong
[VERIFICATION.md](docs/VERIFICATION.md); kết quả theo từng commit nằm ở
[GitHub Actions](https://github.com/Mrsunset9x/vietqr-php/actions).

Package chưa được NAPAS chứng nhận và chưa kiểm thử quét trên app ngân hàng thật.

Giấy phép MIT. Ghi nhận dự án .NET gốc và các thành phần kế thừa trong [NOTICE.md](NOTICE.md).

Người duy trì package có thể xem [quy trình phát hành và đăng ký Packagist](docs/PUBLISHING.md).
