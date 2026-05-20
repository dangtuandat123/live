@extends('layouts.landing')

@section('content')
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 border-b border-border/40 bg-background/80 backdrop-blur-md">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary shadow-lg shadow-primary/25">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 text-primary-foreground">
                            <path d="m22 8-6 4 6 4V8Z" />
                            <rect width="14" height="12" x="2" y="6" rx="2" ry="2" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-foreground flex items-center gap-1.5">
                        {{ config('app.name', 'LiveStream') }}
                        <span class="rounded-md bg-primary/10 px-1.5 py-0.5 text-2xs font-medium text-primary border border-primary/20">AI</span>
                    </span>
                </div>
                
                <div class="hidden md:flex items-center gap-8">
                    <a href="#features" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">Tính năng</a>
                    <a href="#how-it-works" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">Cách hoạt động</a>
                    <a href="#pricing" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">Bảng giá</a>
                </div>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm shadow-primary/20 hover:bg-primary/95 transition-all hover:scale-[1.02]">
                            Bảng điều khiển
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex h-9 items-center justify-center rounded-xl px-4 text-sm font-semibold text-muted-foreground hover:text-foreground transition-colors">
                            Đăng nhập
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-sm shadow-primary/20 hover:bg-primary/95 transition-all hover:scale-[1.02]">
                                Trải nghiệm miễn phí
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative overflow-hidden pt-36 pb-20 sm:pt-44 sm:pb-28">
        <!-- Mesh Gradients Background -->
        <div class="absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[450px] bg-primary/15 dark:bg-primary/10 opacity-60 rounded-full blur-[120px]"></div>
            <div class="absolute top-40 left-1/3 w-[400px] h-[300px] bg-chart-1/10 dark:bg-chart-1/5 opacity-40 rounded-full blur-[90px]"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(128,128,128,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(128,128,128,0.03)_1px,transparent_1px)] bg-[size:32px_32px] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
            <!-- Active Session Badge -->
            <div class="inline-flex items-center gap-2 rounded-full border border-border/80 bg-muted/60 backdrop-blur-md px-4 py-1.5 text-xs font-medium text-muted-foreground mb-8 shadow-xs">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </span>
                Hơn {{ rand(180, 480) }} phiên livestream đang hoạt động trực tuyến
            </div>

            <!-- Main Heading -->
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl lg:text-7xl max-w-5xl mx-auto leading-[1.1] text-foreground">
                Livestream Bán Hàng.
                <br />
                <span class="bg-gradient-to-r from-primary via-primary/90 to-primary/70 bg-clip-text text-transparent">
                    Chốt Đơn Tự Động Bằng AI.
                </span>
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-base text-muted-foreground sm:text-lg">
                Tăng tốc doanh số bán hàng livestream của bạn với hệ thống phân tích bình luận thông minh, nhận diện khách hàng tiềm năng và chốt đơn tự động theo thời gian thực.
            </p>

            <!-- Action CTAs -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex h-11 items-center justify-center rounded-xl bg-primary px-8 text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/25 hover:bg-primary/95 transition-all hover:scale-[1.02] hover:-translate-y-0.5">
                    Dùng thử 14 ngày miễn phí
                    <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
                <a href="#how-it-works" class="w-full sm:w-auto inline-flex h-11 items-center justify-center rounded-xl border border-border bg-background px-8 text-sm font-semibold text-foreground hover:bg-muted/80 transition-colors">
                    Tìm hiểu thêm
                </a>
            </div>

            <!-- Interface Mockup Preview -->
            <div class="relative mt-16 sm:mt-20 mx-auto max-w-5xl rounded-2xl border border-border/80 bg-card p-2 shadow-2xl dark:shadow-primary/5 transition-all duration-300 hover:scale-[1.005]">
                <div class="overflow-hidden rounded-xl border border-border/40 bg-background/50">
                    <img src="{{ asset('images/dashboard_mockup.png') }}" alt="LiveStream AI Dashboard" class="w-full h-auto object-cover select-none" />
                </div>
                <!-- Glass Card Highlights -->
                <div class="absolute -bottom-6 -left-6 hidden lg:block rounded-xl border border-border/60 bg-background/90 backdrop-blur-md p-4 shadow-xl max-w-xs text-left">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500/10 text-green-500">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.519l2.74-1.22m0 0-5.94-2.28m5.94 2.28-2.28 5.941" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground font-medium">Tỷ lệ chuyển đổi đơn</p>
                            <p class="text-lg font-bold text-foreground">+320% bình quân</p>
                        </div>
                    </div>
                </div>
                <div class="absolute -top-6 -right-6 hidden lg:block rounded-xl border border-border/60 bg-background/90 backdrop-blur-md p-4 shadow-xl max-w-xs text-left">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground font-medium">Phản hồi bình luận</p>
                            <p class="text-lg font-bold text-foreground">< 0.5s tức thì</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 border-t border-border/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl text-foreground">Tính năng được thiết kế cho sự đột phá</h2>
                <p class="mt-4 text-base text-muted-foreground">Tự động hóa toàn bộ quy trình bán hàng livestream để bạn có thể tập trung tương tác tốt nhất với khách hàng.</p>
            </div>
            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <!-- Feature 1 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Phân tích hội thoại tự động</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Công nghệ AI xử lý và phân tích ngữ nghĩa bình luận của khách hàng theo thời gian thực để phân loại nhu cầu chốt đơn.</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Chốt đơn tự động</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Tự động nhận diện cú pháp đặt hàng của khách hàng (Mã SP + SĐT) để lên đơn tạm tính và gửi link thanh toán tức thì.</p>
                </div>

                <!-- Feature 3 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Báo cáo hiệu suất Realtime</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Biểu đồ trực quan hóa dữ liệu người xem, số lượng đơn chốt, doanh thu tạm tính và hiệu suất bán hàng liên tục.</p>
                </div>

                <!-- Feature 4 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5 12 12 3.75 7.5M12 12v9m-8.25-12 8.25 4.5 8.25-4.5M3 5.25h18v13.5H3V5.25Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Đồng bộ kho tự động</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Khấu trừ trực tiếp số lượng tồn kho của SKU sản phẩm ngay khi hệ thống chốt đơn thành công để tránh quá tải.</p>
                </div>

                <!-- Feature 5 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94-3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Phân nhóm khách hàng</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Tự động nhận diện khách hàng thân thiết, cảnh báo khách hàng có lịch sử huỷ đơn hoặc bom hàng trực tiếp.</p>
                </div>

                <!-- Feature 6 -->
                <div class="group relative rounded-2xl border border-border bg-card p-8 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                    <div class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9s2.015-9 4-9m0 0a9.003 9.003 0 0 1 8.716 2.253M12 3a9.003 9.003 0 0 0-8.716 2.253M12 12h.008v.008H12V12Z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Kết nối đa kênh dễ dàng</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Liên kết nhanh chóng với TikTok Shop, Facebook Page hoặc hệ thống quản lý đơn hàng ngoại vi thông qua API.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-24 bg-muted/40 border-t border-border/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl text-foreground">Quy trình triển khai tinh gọn</h2>
                <p class="mt-4 text-base text-muted-foreground">Chỉ mất chưa đầy 3 phút để hệ thống AI bắt đầu tiếp quản việc chốt đơn của bạn.</p>
            </div>
            
            <div class="relative grid gap-8 md:grid-cols-3">
                <!-- Visual Connection Line -->
                <div class="absolute top-1/2 left-1/6 right-1/6 h-0.5 bg-border/80 -translate-y-8 hidden md:block -z-10"></div>
                
                <!-- Step 1 -->
                <div class="bg-card border border-border p-8 rounded-2xl text-center relative shadow-xs">
                    <div class="mx-auto -mt-14 mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground shadow-lg shadow-primary/25">
                        01
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Kết nối nguồn Livestream</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Tạo tài khoản và kết nối nền tảng livestream của bạn (ví dụ: TikTok Shop) cực kỳ bảo mật chỉ qua vài cú click chuột.</p>
                </div>

                <!-- Step 2 -->
                <div class="bg-card border border-border p-8 rounded-2xl text-center relative shadow-xs">
                    <div class="mx-auto -mt-14 mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground shadow-lg shadow-primary/25">
                        02
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Chọn Sản phẩm & Từ khóa AI</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Thêm danh sách hàng hóa cần bán, mã SKU và thiết lập từ khóa kịch bản AI nhận diện cú pháp đặt đơn của khách hàng.</p>
                </div>

                <!-- Step 3 -->
                <div class="bg-card border border-border p-8 rounded-2xl text-center relative shadow-xs">
                    <div class="mx-auto -mt-14 mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground shadow-lg shadow-primary/25">
                        03
                    </div>
                    <h3 class="text-lg font-bold text-foreground">Bắt đầu Live & Chốt đơn</h3>
                    <p class="mt-2.5 text-sm leading-relaxed text-muted-foreground">Hệ thống tự động phân tích hội thoại, tự lên đơn nháp và tự nhắn tin dẫn đường link thanh toán cho khách mua hàng.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 border-t border-border/40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl text-foreground">Bảng giá minh bạch</h2>
                <p class="mt-4 text-base text-muted-foreground">Không có chi phí ẩn. Chọn gói tài khoản phù hợp với quy mô kinh doanh của bạn.</p>
            </div>

            <div class="mx-auto grid max-w-5xl gap-8 md:grid-cols-2 lg:max-w-none">
                <!-- Package 1: Basic Trial -->
                <div class="flex flex-col justify-between rounded-3xl border border-border bg-card p-8 shadow-xs">
                    <div>
                        <h3 class="text-lg font-bold text-foreground">Dùng thử miễn phí</h3>
                        <p class="mt-2 text-sm text-muted-foreground">Trải nghiệm hệ thống cơ bản trước khi quyết định đầu tư dài hạn.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-foreground">0đ</span>
                            <span class="text-sm font-medium text-muted-foreground">/ 14 ngày</span>
                        </div>
                        <ul class="mt-8 space-y-4 border-t border-border/60 pt-8 text-sm">
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Giới hạn 3 phiên livestream phân tích</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Phân tích hội thoại thời gian thực</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Quản lý catalog 10 sản phẩm tối đa</span>
                            </li>
                        </ul>
                    </div>
                    <a href="{{ route('register') }}" class="mt-8 inline-flex h-10 items-center justify-center rounded-xl border border-border bg-background text-sm font-semibold text-foreground hover:bg-muted/80 transition-colors">
                        Bắt đầu ngay
                    </a>
                </div>

                <!-- Package 2: Pro Plan -->
                <div class="flex flex-col justify-between rounded-3xl border-2 border-primary bg-card p-8 shadow-xl relative">
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-primary px-4 py-1 text-2xs font-bold uppercase tracking-wider text-primary-foreground">
                        Khuyên dùng nhiều nhất
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-foreground">Gói Chuyên Nghiệp (Pro)</h3>
                        <p class="mt-2 text-sm text-muted-foreground">Giải pháp tối ưu cho cá nhân bán hàng và các hộ kinh doanh trực tuyến.</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-foreground">299.000đ</span>
                            <span class="text-sm font-medium text-muted-foreground">/ tháng</span>
                        </div>
                        <ul class="mt-8 space-y-4 border-t border-border/60 pt-8 text-sm">
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground font-semibold text-foreground">Giới hạn 30 phiên live / tháng</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Nhận dạng AI chốt đơn thông minh</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Không giới hạn sản phẩm SKU catalog</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="h-4.5 w-4.5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-muted-foreground">Trích xuất báo cáo xuất file Excel/PDF</span>
                            </li>
                        </ul>
                    </div>
                    <a href="{{ route('register') }}" class="mt-8 inline-flex h-10 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-primary-foreground shadow-md shadow-primary/25 hover:bg-primary/95 transition-all hover:scale-[1.01]">
                        Đăng ký ngay
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 sm:py-24 border-t border-border/40">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-primary px-8 py-16 text-center shadow-2xl shadow-primary/25 sm:px-16">
                <!-- Decorative background light -->
                <div class="absolute inset-0 -z-10 bg-gradient-to-br from-primary via-primary/90 to-primary/80"></div>
                <div class="absolute top-0 right-0 -mt-12 -mr-12 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
                
                <h2 class="text-3xl font-extrabold tracking-tight text-primary-foreground sm:text-4xl">
                    Đưa doanh thu Livestream của bạn lên tầm cao mới
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-base text-primary-foreground/80">
                    Gia nhập cộng đồng hơn 10.000 chủ shop đã tự động hóa thành công khâu chốt đơn livestream và bùng nổ doanh số.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex h-11 items-center justify-center rounded-xl bg-background px-8 text-sm font-bold text-primary shadow-lg hover:bg-muted transition-all hover:scale-[1.02] hover:-translate-y-0.5">
                        Thử nghiệm miễn phí ngay
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-border/40 py-12 bg-muted/20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-primary-foreground">
                            <path d="m22 8-6 4 6 4V8Z" />
                            <rect width="14" height="12" x="2" y="6" rx="2" ry="2" />
                        </svg>
                    </div>
                    <span class="text-sm font-bold tracking-tight text-foreground">{{ config('app.name', 'LiveStream') }} AI</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    &copy; {{ date('Y') }} {{ config('app.name', 'LiveStream') }} AI. Được thiết kế dành cho các nhà bán hàng hiện đại.
                </p>
            </div>
        </div>
    </footer>
@endsection
