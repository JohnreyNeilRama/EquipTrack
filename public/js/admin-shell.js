// Shared shell behavior for authenticated EquipTrack pages.
// Page-specific scripts run after this file and can reuse showNotification().
// Expects window.navAvatarDbValue and window.navInitials to be set inline by the layout.

document.addEventListener('DOMContentLoaded', () => {
    const bannerDate = document.querySelector('.banner-date');
    if (bannerDate) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        bannerDate.textContent = new Date().toLocaleDateString('en-US', options);
    }
});

// DOM Elements
const themeToggleBtn = document.getElementById('themeToggleBtn');
const themeToggleIcon = document.getElementById('themeToggleIcon');
const userProfileDropdown = document.getElementById('userProfileDropdown');
const dropdownMenu = document.getElementById('dropdownMenu');
const toast = document.getElementById('toast');
const toastIcon = document.getElementById('toastIcon');
const toastTitle = document.getElementById('toastTitle');
const toastMsg = document.getElementById('toastMsg');

// Dark Mode Logic
if (themeToggleBtn && themeToggleIcon) {
    if (document.documentElement.classList.contains('dark-theme')) {
        themeToggleIcon.className = 'fa-solid fa-sun';
    } else {
        themeToggleIcon.className = 'fa-solid fa-moon';
    }

    themeToggleBtn.addEventListener('click', () => {
        const isDark = document.documentElement.classList.toggle('dark-theme');
        if (isDark) {
            themeToggleIcon.className = 'fa-solid fa-sun';
            localStorage.setItem(THEME_KEY, 'dark');
        } else {
            themeToggleIcon.className = 'fa-solid fa-moon';
            localStorage.setItem(THEME_KEY, 'light');
        }
    });
}

// Navbar Avatar Sync Helper (avatar values are server-generated storage URLs)
const NAV_AVATAR_KEY = document.body.dataset.avatarKey || 'admin-avatar-src';
const THEME_KEY = document.body.dataset.themeKey || 'dashboard-theme';

function syncNavbarAvatar(newAvatar) {
    let currentAvatar = null;
    if (newAvatar !== undefined) {
        currentAvatar = newAvatar;
    } else if (window.navAvatarDbValue) {
        currentAvatar = window.navAvatarDbValue;
    } else {
        localStorage.removeItem(NAV_AVATAR_KEY);
        currentAvatar = null;
    }

    const navAvatars = document.querySelectorAll('.user-profile .profile-avatar');

    if (currentAvatar) {
        localStorage.setItem(NAV_AVATAR_KEY, currentAvatar);
        navAvatars.forEach(navAvatar => {
            navAvatar.textContent = '';
            const img = document.createElement('img');
            img.src = currentAvatar;
            img.alt = 'Avatar';
            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover; border-radius: 50%;';
            navAvatar.appendChild(img);
            navAvatar.style.padding = '0';
            navAvatar.style.background = 'transparent';
        });
    } else {
        localStorage.removeItem(NAV_AVATAR_KEY);
        navAvatars.forEach(navAvatar => {
            navAvatar.textContent = window.navInitials;
            navAvatar.style.padding = '';
            navAvatar.style.background = 'var(--primary-color)';
        });
    }
}
syncNavbarAvatar();

window.addEventListener('storage', function(e) {
    if (e.key === NAV_AVATAR_KEY) {
        syncNavbarAvatar(e.newValue);
    }
});

// Toggle user dropdown on click
if (userProfileDropdown && dropdownMenu) {
    userProfileDropdown.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
    });

    // Close dropdown if clicked outside
    document.addEventListener('click', () => {
        dropdownMenu.classList.remove('show');
    });
}

// Show Toast Function
function showNotification(title, message, type = 'success') {
    if (!toast) return;
    toastTitle.textContent = title;
    toastMsg.textContent = message;

    // Set style based on status
    if (type === 'success') {
        toastIcon.className = 'fa-solid fa-circle-check toast-icon';
        toastIcon.style.color = '#10b981';
        document.querySelector('.toast-content').style.borderLeft = '4px solid #10b981';
    } else {
        toastIcon.className = 'fa-solid fa-circle-xmark toast-icon';
        toastIcon.style.color = '#ef4444';
        document.querySelector('.toast-content').style.borderLeft = '4px solid #ef4444';
    }

    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}
