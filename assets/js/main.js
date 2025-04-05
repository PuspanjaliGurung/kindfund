/**
 * KindFund Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeComponents();
    
    // Set up event listeners
    setupEventListeners();
    
    // Animate elements when they come into view
    setupScrollAnimations();
});

/**
 * Initialize UI components
 */
function initializeComponents() {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', showTooltip);
        tooltip.addEventListener('mouseleave', hideTooltip);
    });
    
    // Initialize tabs if they exist
    const tabContainers = document.querySelectorAll('.tabs-container');
    tabContainers.forEach(container => {
        initializeTabs(container);
    });
    
    // Initialize donation amount selector if it exists
    const donationForm = document.getElementById('donation-form');
    if (donationForm) {
        initializeDonationForm(donationForm);
    }
    
    // Initialize progress bars with animation
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const percentValue = bar.getAttribute('data-percent') || '0';
        bar.style.width = '0%';
        
        // Small delay to ensure animation works
        setTimeout(() => {
            bar.style.width = percentValue + '%';
        }, 100);
    });
}

/**
 * Set up global event listeners
 */
function setupEventListeners() {
    // Form validation
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
    });
    
    // Toggle mobile menu
    const mobileMenuBtn = document.getElementById('mobile-menu-button');
    const mainNav = document.getElementById('main-nav');
    
    if (mobileMenuBtn && mainNav) {
        mobileMenuBtn.addEventListener('click', () => {
            mainNav.classList.toggle('hidden');
        });
    }
    
    // Handle custom dropdowns
    const dropdownTriggers = document.querySelectorAll('.dropdown-trigger');
    dropdownTriggers.forEach(trigger => {
        trigger.addEventListener('click', toggleDropdown);
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown-container')) {
            const openDropdowns = document.querySelectorAll('.dropdown-menu:not(.hidden)');
            openDropdowns.forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
        }
    });
}

/**
 * Setup animations triggered by scrolling
 */
function setupScrollAnimations() {
    const animatedElements = document.querySelectorAll('.animate-on-scroll');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    animatedElements.forEach(element => {
        observer.observe(element);
    });
}

/**
 * Show tooltip
 */
function showTooltip(e) {
    const tooltip = e.target;
    const text = tooltip.getAttribute('data-tooltip');
    
    // Create tooltip element
    const tooltipEl = document.createElement('div');
    tooltipEl.className = 'bg-gray-800 text-white text-xs rounded py-1 px-2 absolute z-10 opacity-0 transition-opacity duration-300';
    tooltipEl.innerHTML = text;
    tooltipEl.style.bottom = '100%';
    tooltipEl.style.left = '50%';
    tooltipEl.style.transform = 'translateX(-50%)';
    tooltipEl.style.marginBottom = '5px';
    
    // Add arrow
    const arrow = document.createElement('div');
    arrow.className = 'tooltip-arrow';
    arrow.style.position = 'absolute';
    arrow.style.top = '100%';
    arrow.style.left = '50%';
    arrow.style.marginLeft = '-5px';
    arrow.style.borderWidth = '5px';
    arrow.style.borderStyle = 'solid';
    arrow.style.borderColor = 'rgb(31, 41, 55) transparent transparent transparent';
    tooltipEl.appendChild(arrow);
    
    // Add to DOM
    tooltip.style.position = 'relative';
    tooltip.appendChild(tooltipEl);
    
    // Show with small delay
    setTimeout(() => {
        tooltipEl.style.opacity = '1';
    }, 10);
}

/**
 * Hide tooltip
 */
function hideTooltip(e) {
    const tooltip = e.target;
    const tooltipEl = tooltip.querySelector('div');
    
    if (tooltipEl) {
        tooltipEl.style.opacity = '0';
        setTimeout(() => {
            tooltipEl.remove();
        }, 300);
    }
}

/**
 * Initialize tabs
 */
function initializeTabs(container) {
    const tabs = container.querySelectorAll('.tab');
    const tabContents = container.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab');
            
            // Update active tab
            tabs.forEach(t => t.classList.remove('active-tab'));
            tab.classList.add('active-tab');
            
            // Show appropriate content
            tabContents.forEach(content => {
                if (content.getAttribute('data-tab-content') === target) {
                    content.classList.remove('hidden');
                } else {
                    content.classList.add('hidden');
                }
            });
        });
    });
}

/**
 * Initialize donation form
 */
function initializeDonationForm(form) {
    const amountOptions = form.querySelectorAll('.amount-option');
    const customAmountInput = form.querySelector('#custom-amount');
    
    // Handle amount selection
    amountOptions.forEach(option => {
        option.addEventListener('click', () => {
            // Remove active class from all options
            amountOptions.forEach(opt => opt.classList.remove('active'));
            
            // Add active class to selected option
            option.classList.add('active');
            
            // Update hidden input with selected amount
            const selectedAmount = option.getAttribute('data-amount');
            const amountInput = form.querySelector('input[name="amount"]');
            amountInput.value = selectedAmount;
            
            // Clear custom amount
            if (customAmountInput) {
                customAmountInput.value = '';
            }
        });
    });
    
    // Handle custom amount input
    if (customAmountInput) {
        customAmountInput.addEventListener('input', () => {
            // Remove active class from predefined options
            amountOptions.forEach(opt => opt.classList.remove('active'));
            
            // Update hidden input with custom amount
            const amountInput = form.querySelector('input[name="amount"]');
            amountInput.value = customAmountInput.value;
        });
    }
}

/**
 * Form validation
 */
function validateForm(e) {
    const form = e.target;
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    // Clear previous error messages
    const errorMessages = form.querySelectorAll('.error-message');
    errorMessages.forEach(msg => msg.remove());
    
    // Check all required fields
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            showValidationError(field, 'This field is required');
        } else if (field.type === 'email' && !isValidEmail(field.value)) {
            isValid = false;
            showValidationError(field, 'Please enter a valid email address');
        } else if (field.type === 'tel' && !isValidPhone(field.value)) {
            isValid = false;
            showValidationError(field, 'Please enter a valid phone number');
        } else if (field.id === 'password-confirm' && field.value !== form.querySelector('#password').value) {
            isValid = false;
            showValidationError(field, 'Passwords do not match');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
    }
}

/**
 * Show validation error
 */
function showValidationError(field, message) {
    field.classList.add('border-red-500');
    
    const errorElement = document.createElement('p');
    errorElement.className = 'text-red-500 text-xs mt-1 error-message';
    errorElement.innerText = message;
    
    // Insert after the field
    field.parentNode.insertBefore(errorElement, field.nextSibling);
    
    // Remove error on input
    field.addEventListener('input', () => {
        field.classList.remove('border-red-500');
        const error = field.parentNode.querySelector('.error-message');
        if (error) {
            error.remove();
        }
    }, { once: true });
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

/**
 * Validate phone number format
 */
function isValidPhone(phone) {
    const re = /^\+?[0-9]{10,15}$/;
    return re.test(String(phone).replace(/[\s()+\-\.]|ext/gi, ''));
}

/**
 * Toggle dropdown menu
 */
function toggleDropdown(e) {
    const trigger = e.currentTarget;
    const dropdownMenu = trigger.nextElementSibling;
    
    // Close other open dropdowns
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    allDropdowns.forEach(dropdown => {
        if (dropdown !== dropdownMenu) {
            dropdown.classList.add('hidden');
        }
    });
    
    // Toggle this dropdown
    dropdownMenu.classList.toggle('hidden');
    e.stopPropagation();
}