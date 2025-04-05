-- Create Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    profile_image VARCHAR(255),
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    user_type ENUM('donor', 'charity_admin', 'admin') NOT NULL DEFAULT 'donor',
    is_active BOOLEAN DEFAULT TRUE
);

-- Create Charities table
CREATE TABLE charities (
    charity_id INT AUTO_INCREMENT PRIMARY KEY,
    charity_name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    logo VARCHAR(255),
    website VARCHAR(255),
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    registration_number VARCHAR(50),
    admin_id INT,
    creation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_verified BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Create Campaigns table
CREATE TABLE campaigns (
    campaign_id INT AUTO_INCREMENT PRIMARY KEY,
    charity_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    goal_amount DECIMAL(12,2) NOT NULL,
    current_amount DECIMAL(12,2) DEFAULT 0.00,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    campaign_image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    creation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (charity_id) REFERENCES charities(charity_id) ON DELETE CASCADE
);

-- Create Categories table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    description TEXT
);

-- Create Campaign_Categories relation table
CREATE TABLE campaign_categories (
    campaign_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (campaign_id, category_id),
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
);

-- Create Donations table
CREATE TABLE donations (
    donation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    campaign_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    donation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(100),
    is_anonymous BOOLEAN DEFAULT FALSE,
    message TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE
);

-- Create Events table
CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    charity_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    event_date DATETIME NOT NULL,
    location TEXT NOT NULL,
    event_image VARCHAR(255),
    max_participants INT,
    registration_deadline DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    creation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (charity_id) REFERENCES charities(charity_id) ON DELETE CASCADE
);

-- Create Event_Registrations table
CREATE TABLE event_registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    attendance_status ENUM('registered', 'attended', 'cancelled') DEFAULT 'registered',
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create Volunteer_Opportunities table
CREATE TABLE volunteer_opportunities (
    opportunity_id INT AUTO_INCREMENT PRIMARY KEY,
    charity_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    location TEXT,
    skills_required TEXT,
    max_volunteers INT,
    is_active BOOLEAN DEFAULT TRUE,
    creation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (charity_id) REFERENCES charities(charity_id) ON DELETE CASCADE
);

-- Create Volunteer_Applications table
CREATE TABLE volunteer_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT NOT NULL,
    user_id INT NOT NULL,
    application_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    message TEXT,
    FOREIGN KEY (opportunity_id) REFERENCES volunteer_opportunities(opportunity_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Insert sample categories
INSERT INTO categories (category_name, description) VALUES 
('Education', 'Educational initiatives and academic support'),
('Healthcare', 'Medical services and health awareness programs'),
('Environment', 'Conservation and sustainability projects'),
('Disaster Relief', 'Emergency aid and recovery assistance'),
('Poverty Alleviation', 'Programs to combat poverty and economic hardship'),
('Animal Welfare', 'Protection and care for animals'),
('Arts & Culture', 'Promotion of arts, heritage, and cultural programs'),
('Human Rights', 'Advocacy for social justice and equality');

-- Insert admin user
INSERT INTO users (username, email, password, first_name, last_name, user_type) VALUES 
('admin', 'admin@kindfund.org', '$2y$10$hKl7.RrK0bqhiQQBzrVZ1uXu69Z7GH0alnOE40bNGN9AjAr2jQ7K.', 'Admin', 'User', 'admin');
-- The password is 'admin123' - hashed with bcrypt