# Hướng dẫn sử dụng VietQR PHP

Hướng dẫn này dành cho ứng dụng đã cài package theo [README](../README.md#cài-đặt).
Các số tài khoản bên dưới là dữ liệu ví dụ; cần thay bằng thông tin người nhận thực tế.

## Từ đơn hàng đến mã QR

Trong PHP thuần, nạp `vendor/autoload.php` một lần tại điểm khởi đầu ứng dụng.
Laravel đã làm bước này sẵn.

```php
require __DIR__ . '/vendor/autoload.php';

$payment = new \VietQr\Payment(
    bankBin: '970403',
    accountNo: '0011012345678',
    amount: '180000',
    description: 'DH000123',
    billNumber: 'HD000123',
    reference: 'DH000123',
);

$payload = \VietQr\VietQr::generate($payment);
```

`description` là nội dung chuyển khoản, `billNumber` là số hóa đơn và `reference` là
mã tham chiếu. Chúng nằm ở các trường khác nhau trong QR. Cần kiểm tra app/ngân hàng
đích hiển thị hoặc chuyển tiếp những trường nào trước khi dùng chúng để đối soát.

Giữ số tiền VND nguyên dưới dạng `int` hoặc chuỗi chữ số. Đừng ép một số thực thành
`int` để vượt kiểm tra, vì phần lẻ có thể đã mất trước khi package nhận dữ liệu.
Trên PHP 32-bit, dùng chuỗi cho số vượt giới hạn số nguyên.

Bỏ `amount` khi muốn khách tự nhập tiền. Để chuyển tới thẻ, truyền thêm
`service: \VietQr\Service::Card`; khi đó `accountNo` chứa số thẻ và chỉ nhận chữ số.
Loại QR mặc định là động khi có tiền, tĩnh khi không có. Nếu cần chọn rõ, truyền
`type: \VietQr\QrType::Static` hoặc `type: \VietQr\QrType::Dynamic`.

Cờ tĩnh/động chỉ mô tả phương thức khởi tạo. Việc hết hạn đơn hàng, thanh toán lặp và
xác nhận số tiền thuộc trách nhiệm của ứng dụng và bên xử lý giao dịch.

## Xuất ảnh và trả về trình duyệt

Cài `bacon/bacon-qr-code:~3.0` và các extension tương ứng như trong README.

```php
$renderer = new \VietQr\Rendering\BaconQrRenderer();
$svg = $renderer->svg($payment, 512);
$png = $renderer->png($payment, 512);
```

Trả SVG trong một endpoint PHP:

```php
header('Content-Type: image/svg+xml');
echo $renderer->svg($payment);
```

Hoặc tạo data URI để dùng với thẻ `<img>`:

```php
$dataUri = 'data:image/png;base64,' . base64_encode($renderer->png($payment));
echo '<img src="' . $dataUri . '" alt="Mã chuyển khoản" width="320" height="320">';
```

Khi nội dung QR không thay đổi, ứng dụng có thể cache ảnh theo chuỗi payload và kích
thước. Dùng SVG khi cần co giãn hoặc in. Không cắt bỏ vùng trắng quanh mã hay chèn
logo đè lên các ô QR nếu chưa kiểm tra khả năng đọc lại.

## Ví dụ trong Laravel

Laravel có thể gọi trực tiếp các lớp trong package. Ví dụ một phương thức controller
nhận `$order` từ ứng dụng:

```php
use VietQr\Payment;
use VietQr\Rendering\BaconQrRenderer;

public function qr(Order $order)
{
    $this->authorize('view', $order);

    $payment = new Payment(
        bankBin: (string) config('payments.bank_bin'),
        accountNo: (string) config('payments.account_no'),
        amount: $order->total_vnd,
        description: $order->payment_reference,
    );

    return response((new BaconQrRenderer())->svg($payment), 200, [
        'Content-Type' => 'image/svg+xml',
        'Cache-Control' => 'private, no-store',
    ]);
}
```

Đây là đoạn minh họa để đặt vào controller của ứng dụng. `Order`, policy `view`, các
trường đơn hàng và file cấu hình `payments` do ứng dụng cung cấp. Chọn quyền phù hợp
luồng thanh toán; `total_vnd` phải là số VND nguyên và `payment_reference` không quá
25 ký tự sau khi bỏ dấu. Lấy tài khoản nhận tiền và số tiền từ cấu hình/đơn hàng đã
xác minh ở máy chủ, thay vì tin trực tiếp dữ liệu trình duyệt gửi lên.

Ví dụ này chưa được chạy trong một ứng dụng Laravel cụ thể. Bộ kiểm thử của package
tập trung vào phần PHP độc lập.

## Giữ nguyên dữ liệu khi đọc mã

```php
$result = \VietQr\VietQr::parse($payload);

$payment = $result->payment;
$original = $result->payload;
$rootFields = $result->tags;
```

`parse()` nhận chuỗi, không nhận file ảnh. Trường chưa biết vẫn được giữ trong chuỗi
gốc và `tags`, nhưng không được diễn giải hay xác nhận hợp lệ về nghiệp vụ.

Muốn chuyển tiếp mã mà không đổi dữ liệu, dùng `$result->payload`.
`VietQr::generate($result->payment)` chỉ tạo lại các trường package hỗ trợ, nên có
thể bỏ các trường khác và chuẩn hóa nội dung. Ví dụ, tiền `50000.` khi đọc sẽ trở
thành chuỗi `'50000'`; API tạo mới chỉ nhận số nguyên hoặc chuỗi chữ số.

## Xử lý lỗi thường gặp

```php
try {
    $payment = new \VietQr\Payment('970403', '0011012345678', amount: '180000');
    $payload = \VietQr\VietQr::generate($payment);
} catch (\VietQr\InvalidVietQr $error) {
    // Trả thông báo kiểm tra dữ liệu phù hợp giao diện của ứng dụng.
}
```

`InvalidVietQr` kế thừa `InvalidArgumentException`. Truyền sai kiểu tham số PHP vẫn
có thể gây `TypeError`; ứng dụng nên kiểm tra dữ liệu request trước khi gọi.

| Tình huống | Cách xử lý |
|---|---|
| Nội dung hoặc mã đơn quá dài | Chọn mã tham chiếu tối đa 25 ký tự; không tự cắt mã dùng để đối soát |
| Số tiền bị từ chối | Kiểm tra kiểu `int`/chuỗi chữ số, giá trị dương và độ dài; không truyền chuỗi đã định dạng tiền để hiển thị |
| Tài khoản mất số 0 đầu | Giữ dạng chuỗi từ lúc nhập/lưu; package không khôi phục được số 0 đã mất trước đó |
| Thiếu BaconQrCode | Cài dependency xuất ảnh theo README hoặc dùng bộ mã hóa QR sẵn có |
| Thiếu extension | Bật `gd`, `xmlwriter` hoặc `iconv` trong đúng PHP runtime; cấu hình PHP CLI và web có thể khác nhau |
| Kích thước ảnh bị từ chối | Chọn từ 256 đến 2.048 px |
| CRC không khớp | Lấy lại chuỗi QR gốc; không tự sửa CRC để ép parser chấp nhận dữ liệu |
| Có QR nhưng đơn chưa được xác nhận | Đối chiếu giao dịch qua ngân hàng/nhà cung cấp; tạo QR không sinh thông báo tiền về |

Văn bản tùy chọn nhận tối đa 1.024 byte trước chuẩn hóa. Chuỗi QR đầu vào cũng có
giới hạn 1.024 byte và chỉ chấp nhận ASCII in được. Ký tự điều khiển, emoji và các
hệ chữ chưa hỗ trợ sẽ bị từ chối. Giới hạn từng trường nằm trong README.

## Làm việc với source local

Nếu đang sửa package cùng ứng dụng, dùng Composer path repository thay cho GitHub.
Gộp mục sau vào `composer.json` của ứng dụng, chỉnh `url` theo vị trí checkout:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../vietqr-php",
            "options": {
                "symlink": true,
                "versions": { "mrsunset9x/vietqr-php": "dev-main" }
            }
        }
    ]
}
```

Sau đó chạy `composer require "mrsunset9x/vietqr-php:dev-main"`. Path repository dùng
symlink/junction cho phát triển. Khi triển khai, dùng bản từ GitHub với lockfile của
ứng dụng hoặc tạo ZIP bằng `composer archive --format=zip --dir=build`. Tránh chép
cả `vendor` của môi trường phát triển package vào sản phẩm.
