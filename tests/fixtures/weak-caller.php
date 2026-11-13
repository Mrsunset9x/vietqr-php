<?php

// Intentionally no strict_types: production callers may not enable it.
return static fn ($amount) => new \VietQr\Payment('970403', '001', amount: $amount);
