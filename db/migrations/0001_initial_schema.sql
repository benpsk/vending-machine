create table if not exists schema_migrations (
    filename varchar(255) not null primary key,
    applied_at timestamp not null default current_timestamp
) engine=innodb default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists users (
    id bigint unsigned not null auto_increment primary key,
    username varchar(50) not null,
    email varchar(255) not null,
    password_hash varchar(255) not null,
    role enum('admin', 'user') not null default 'user',
    created_at timestamp not null default current_timestamp,
    updated_at timestamp not null default current_timestamp on update current_timestamp,
    constraint uq_users_username unique (username),
    constraint uq_users_email unique (email)
) engine=innodb default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists products (
    id bigint unsigned not null auto_increment primary key,
    name varchar(100) not null,
    price decimal(10, 3) not null,
    quantity_available int unsigned not null default 0,
    created_at timestamp not null default current_timestamp,
    updated_at timestamp not null default current_timestamp on update current_timestamp,
    constraint chk_products_price_positive check (price > 0),
    key idx_products_name (name)
) engine=innodb default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists transactions (
    id bigint unsigned not null auto_increment primary key,
    user_id bigint unsigned not null,
    product_id bigint unsigned not null,
    quantity int unsigned not null,
    unit_price decimal(10, 3) not null,
    total_amount decimal(12, 3) not null,
    created_at timestamp not null default current_timestamp,
    constraint chk_transactions_quantity_positive check (quantity > 0),
    constraint fk_transactions_user foreign key (user_id) references users (id) on delete restrict,
    constraint fk_transactions_product foreign key (product_id) references products (id) on delete restrict,
    key idx_tx_user (user_id, created_at),
    key idx_tx_product (product_id, created_at)
) engine=innodb default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table login_attempts (
    id bigint unsigned not null auto_increment primary key,
    ip varchar(45) not null,
    attempted_at timestamp not null default current_timestamp,
    success tinyint(1) not null default 0,
    key idx_login_attempts_ip_time (ip, attempted_at)
) engine=innodb default charset=utf8mb4 collate=utf8mb4_unicode_ci;
