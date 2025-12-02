import React, { useState } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { motion } from 'framer-motion';
import { 
  Bell, 
  Shield, 
  CreditCard, 
  Globe, 
  Moon, 
  Trash2,
  Save,
  Eye,
  EyeOff,
  CheckCircle,
  XCircle
} from 'lucide-react';
import { toast } from 'react-hot-toast';
// import { signout } from '../store//authSlice';

const Settings = () => {
  const { user } = useSelector((state) => state.auth);
  const dispatch = useDispatch();
  const [activeSection, setActiveSection] = useState('account');
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const [accountSettings, setAccountSettings] = useState({
    firstName: 'John',
    lastName: 'Doe',
    email: 'john.doe@example.com',
    phone: '+1 (555) 123-4567',
    language: 'en',
    currency: 'USD'
  });

  const [notificationSettings, setNotificationSettings] = useState({
    emailNotifications: true,
    pushNotifications: true,
    smsNotifications: false,
    orderUpdates: true,
    promotions: false,
    priceDrops: true,
    newArrivals: true
  });

  const [securitySettings, setSecuritySettings] = useState({
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
    twoFactorAuth: false
  });

  const [privacySettings, setPrivacySettings] = useState({
    profileVisibility: 'public',
    showOnlineStatus: true,
    allowMessages: true,
    dataSharing: false
  });

  const sections = [
    { id: 'account', label: 'Account Settings', icon: Globe },
    { id: 'notifications', label: 'Notifications', icon: Bell },
    { id: 'security', label: 'Security', icon: Shield },
    { id: 'privacy', label: 'Privacy', icon: Moon },
    { id: 'billing', label: 'Billing', icon: CreditCard }
  ];

  const validateEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  const validatePhone = (phone) => {
    const phoneRegex = /^\+?[\d\s-()]{10,}$/;
    return phoneRegex.test(phone);
  };

  const validatePassword = (password) => {
    return password.length >= 8;
  };

  const handleSaveAccountSettings = () => {
    if (!accountSettings.firstName.trim() || !accountSettings.lastName.trim()) {
      toast.error('First name and last name are required');
      return;
    }

    if (!validateEmail(accountSettings.email)) {
      toast.error('Please enter a valid email address');
      return;
    }

    if (accountSettings.phone && !validatePhone(accountSettings.phone)) {
      toast.error('Please enter a valid phone number');
      return;
    }

    toast.success('Account settings updated successfully!');
  };

  const handleChangePassword = () => {
    if (!securitySettings.currentPassword) {
      toast.error('Please enter your current password');
      return;
    }

    if (!validatePassword(securitySettings.newPassword)) {
      toast.error('Password must be at least 8 characters long');
      return;
    }

    if (securitySettings.newPassword !== securitySettings.confirmPassword) {
      toast.error('New passwords do not match');
      return;
    }

    // Simulate API call
    toast.success('Password changed successfully!');
    setSecuritySettings({
      currentPassword: '',
      newPassword: '',
      confirmPassword: '',
      twoFactorAuth: securitySettings.twoFactorAuth
    });
  };

  const handleDeleteAccount = () => {
    if (window.confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
      // Simulate account deletion
      toast.success('Account deletion scheduled. You will receive a confirmation email.');
    }
  };

  const getPasswordStrength = (password) => {
    if (!password) return { strength: '', color: 'gray', width: '0%' };
    
    let score = 0;
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?]/.test(password)) score++;

    const strengths = [
      { strength: "Very Weak", color: "red", width: "20%" },
      { strength: "Weak", color: "orange", width: "40%" },
      { strength: "Fair", color: "yellow", width: "60%" },
      { strength: "Good", color: "blue", width: "80%" },
      { strength: "Strong", color: "green", width: "100%" }
    ];

    return strengths[score] || strengths[0];
  };

  const passwordStrength = getPasswordStrength(securitySettings.newPassword);

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          className="space-y-8"
        >
          {/* Header */}
          <div className="bg-white rounded-2xl shadow-sm p-6">
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Settings</h1>
            <p className="text-gray-600">Manage your account settings and preferences</p>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
            {/* Sidebar */}
            <div className="lg:col-span-1">
              <div className="bg-white rounded-2xl shadow-sm p-6 space-y-2 sticky top-8">
                {sections.map((section) => (
                  <button
                    key={section.id}
                    onClick={() => setActiveSection(section.id)}
                    className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors ${
                      activeSection === section.id
                        ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700'
                        : 'text-gray-600 hover:bg-gray-50'
                    }`}
                  >
                    <section.icon className="h-5 w-5" />
                    <span className="font-medium">{section.label}</span>
                  </button>
                ))}
              </div>
            </div>

            {/* Content Area */}
            <div className="lg:col-span-3 space-y-6">
              {/* Account Settings */}
              {activeSection === 'account' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <div className="bg-white rounded-2xl shadow-sm p-6">
                    <h2 className="text-xl font-semibold text-gray-900 mb-6">Account Information</h2>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          First Name *
                        </label>
                        <input
                          type="text"
                          value={accountSettings.firstName}
                          onChange={(e) => setAccountSettings({...accountSettings, firstName: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                      
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Last Name *
                        </label>
                        <input
                          type="text"
                          value={accountSettings.lastName}
                          onChange={(e) => setAccountSettings({...accountSettings, lastName: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                      
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Email Address *
                        </label>
                        <input
                          type="email"
                          value={accountSettings.email}
                          onChange={(e) => setAccountSettings({...accountSettings, email: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                      
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Phone Number
                        </label>
                        <input
                          type="tel"
                          value={accountSettings.phone}
                          onChange={(e) => setAccountSettings({...accountSettings, phone: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                      
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Language
                        </label>
                        <select
                          value={accountSettings.language}
                          onChange={(e) => setAccountSettings({...accountSettings, language: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                          <option value="en">English</option>
                          <option value="es">Spanish</option>
                          <option value="fr">French</option>
                          <option value="de">German</option>
                        </select>
                      </div>
                      
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Currency
                        </label>
                        <select
                          value={accountSettings.currency}
                          onChange={(e) => setAccountSettings({...accountSettings, currency: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                          <option value="USD">USD ($)</option>
                          <option value="EUR">EUR (€)</option>
                          <option value="GBP">GBP (£)</option>
                          <option value="JPY">JPY (¥)</option>
                        </select>
                      </div>
                    </div>
                    
                    <div className="flex space-x-3 pt-6 border-t border-gray-200">
                      <button
                        onClick={handleSaveAccountSettings}
                        className="flex items-center space-x-2 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                      >
                        <Save className="h-4 w-4" />
                        <span>Save Changes</span>
                      </button>
                    </div>
                  </div>
                </motion.div>
              )}

              {/* Notification Settings */}
              {activeSection === 'notifications' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <div className="bg-white rounded-2xl shadow-sm p-6">
                    <h2 className="text-xl font-semibold text-gray-900 mb-6">Notification Preferences</h2>
                    
                    <div className="space-y-6">
                      <div>
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Notification Channels</h3>
                        <div className="space-y-4">
                          {[
                            { key: 'emailNotifications', label: 'Email Notifications', description: 'Receive notifications via email' },
                            { key: 'pushNotifications', label: 'Push Notifications', description: 'Receive push notifications on your device' },
                            { key: 'smsNotifications', label: 'SMS Notifications', description: 'Receive notifications via SMS' }
                          ].map((item) => (
                            <div key={item.key} className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                              <div>
                                <p className="font-medium text-gray-900">{item.label}</p>
                                <p className="text-sm text-gray-600">{item.description}</p>
                              </div>
                              <button
                                onClick={() => setNotificationSettings({
                                  ...notificationSettings,
                                  [item.key]: !notificationSettings[item.key]
                                })}
                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                  notificationSettings[item.key] ? 'bg-blue-600' : 'bg-gray-200'
                                }`}
                              >
                                <span
                                  className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                    notificationSettings[item.key] ? 'translate-x-6' : 'translate-x-1'
                                  }`}
                                />
                              </button>
                            </div>
                          ))}
                        </div>
                      </div>
                      
                      <div className="border-t border-gray-200 pt-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Notification Types</h3>
                        <div className="space-y-4">
                          {[
                            { key: 'orderUpdates', label: 'Order Updates', description: 'Get notified about your order status' },
                            { key: 'promotions', label: 'Promotions & Offers', description: 'Receive special offers and discounts' },
                            { key: 'priceDrops', label: 'Price Drop Alerts', description: 'Get notified when items in your wishlist go on sale' },
                            { key: 'newArrivals', label: 'New Arrivals', description: 'Be the first to know about new products' }
                          ].map((item) => (
                            <div key={item.key} className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                              <div>
                                <p className="font-medium text-gray-900">{item.label}</p>
                                <p className="text-sm text-gray-600">{item.description}</p>
                              </div>
                              <button
                                onClick={() => setNotificationSettings({
                                  ...notificationSettings,
                                  [item.key]: !notificationSettings[item.key]
                                })}
                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                  notificationSettings[item.key] ? 'bg-blue-600' : 'bg-gray-200'
                                }`}
                              >
                                <span
                                  className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                    notificationSettings[item.key] ? 'translate-x-6' : 'translate-x-1'
                                  }`}
                                />
                              </button>
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  </div>
                </motion.div>
              )}

              {/* Security Settings */}
              {activeSection === 'security' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <div className="bg-white rounded-2xl shadow-sm p-6">
                    <h2 className="text-xl font-semibold text-gray-900 mb-6">Security Settings</h2>
                    
                    <div className="space-y-6">
                      {/* Change Password */}
                      <div className="border-b border-gray-200 pb-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Change Password</h3>
                        
                        <div className="space-y-4">
                          <div className="relative">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                              Current Password
                            </label>
                            <input
                              type={showCurrentPassword ? "text" : "password"}
                              value={securitySettings.currentPassword}
                              onChange={(e) => setSecuritySettings({...securitySettings, currentPassword: e.target.value})}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                            />
                            <button
                              type="button"
                              onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                              className="absolute right-3 top-9 text-gray-400 hover:text-gray-600"
                            >
                              {showCurrentPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                          </div>
                          
                          <div className="relative">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                              New Password
                            </label>
                            <input
                              type={showNewPassword ? "text" : "password"}
                              value={securitySettings.newPassword}
                              onChange={(e) => setSecuritySettings({...securitySettings, newPassword: e.target.value})}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                            />
                            <button
                              type="button"
                              onClick={() => setShowNewPassword(!showNewPassword)}
                              className="absolute right-3 top-9 text-gray-400 hover:text-gray-600"
                            >
                              {showNewPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                          </div>
                          
                          {securitySettings.newPassword && (
                            <div className="mb-4">
                              <div className="flex justify-between items-center mb-2">
                                <span className="text-sm text-gray-600">Password Strength:</span>
                                <span className={`text-sm font-medium text-${passwordStrength.color}-600`}>
                                  {passwordStrength.strength}
                                </span>
                              </div>
                              <div className="w-full bg-gray-200 rounded-full h-2">
                                <div
                                  className={`bg-${passwordStrength.color}-600 h-2 rounded-full transition-all duration-300`}
                                  style={{ width: passwordStrength.width }}
                                ></div>
                              </div>
                            </div>
                          )}
                          
                          <div className="relative">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                              Confirm New Password
                            </label>
                            <input
                              type={showConfirmPassword ? "text" : "password"}
                              value={securitySettings.confirmPassword}
                              onChange={(e) => setSecuritySettings({...securitySettings, confirmPassword: e.target.value})}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                            />
                            <button
                              type="button"
                              onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                              className="absolute right-3 top-9 text-gray-400 hover:text-gray-600"
                            >
                              {showConfirmPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                          </div>
                          
                          <button
                            onClick={handleChangePassword}
                            className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                          >
                            Change Password
                          </button>
                        </div>
                      </div>
                      
                      {/* Two-Factor Authentication */}
                      <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div>
                          <p className="font-medium text-gray-900">Two-Factor Authentication</p>
                          <p className="text-sm text-gray-600">Add an extra layer of security to your account</p>
                        </div>
                        <button
                          onClick={() => setSecuritySettings({
                            ...securitySettings,
                            twoFactorAuth: !securitySettings.twoFactorAuth
                          })}
                          className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                            securitySettings.twoFactorAuth ? 'bg-blue-600' : 'bg-gray-200'
                          }`}
                        >
                          <span
                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                              securitySettings.twoFactorAuth ? 'translate-x-6' : 'translate-x-1'
                            }`}
                          />
                        </button>
                      </div>
                      
                      {/* Session Management */}
                      <div>
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Active Sessions</h3>
                        <div className="space-y-3">
                          <div className="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                            <div className="flex items-center space-x-3">
                              <CheckCircle className="h-5 w-5 text-green-600" />
                              <div>
                                <p className="font-medium text-gray-900">Current Session</p>
                                <p className="text-sm text-gray-600">Chrome • Windows • New York, US</p>
                              </div>
                            </div>
                            <span className="text-sm text-green-600 font-medium">Active</span>
                          </div>
                          
                          <div className="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                            <div className="flex items-center space-x-3">
                              <XCircle className="h-5 w-5 text-gray-400" />
                              <div>
                                <p className="font-medium text-gray-900">Previous Session</p>
                                <p className="text-sm text-gray-600">Safari • macOS • Los Angeles, US</p>
                              </div>
                            </div>
                            <span className="text-sm text-gray-600">2 hours ago</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </motion.div>
              )}

              {/* Privacy Settings */}
              {activeSection === 'privacy' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <div className="bg-white rounded-2xl shadow-sm p-6">
                    <h2 className="text-xl font-semibold text-gray-900 mb-6">Privacy Settings</h2>
                    
                    <div className="space-y-6">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                          Profile Visibility
                        </label>
                        <select
                          value={privacySettings.profileVisibility}
                          onChange={(e) => setPrivacySettings({...privacySettings, profileVisibility: e.target.value})}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                          <option value="public">Public</option>
                          <option value="friends">Friends Only</option>
                          <option value="private">Private</option>
                        </select>
                      </div>
                      
                      <div className="space-y-4">
                        {[
                          { key: 'showOnlineStatus', label: 'Show Online Status', description: 'Allow others to see when you are online' },
                          { key: 'allowMessages', label: 'Allow Direct Messages', description: 'Allow other users to send you direct messages' },
                          { key: 'dataSharing', label: 'Data Sharing for Analytics', description: 'Help us improve by sharing anonymous usage data' }
                        ].map((item) => (
                          <div key={item.key} className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div>
                              <p className="font-medium text-gray-900">{item.label}</p>
                              <p className="text-sm text-gray-600">{item.description}</p>
                            </div>
                            <button
                              onClick={() => setPrivacySettings({
                                ...privacySettings,
                                [item.key]: !privacySettings[item.key]
                              })}
                              className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                privacySettings[item.key] ? 'bg-blue-600' : 'bg-gray-200'
                              }`}
                            >
                              <span
                                className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                  privacySettings[item.key] ? 'translate-x-6' : 'translate-x-1'
                                }`}
                              />
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                  
                  {/* Account Deletion */}
                  <div className="bg-white rounded-2xl shadow-sm p-6 border border-red-200">
                    <h2 className="text-xl font-semibold text-red-900 mb-4">Danger Zone</h2>
                    <p className="text-gray-600 mb-4">
                      Once you delete your account, there is no going back. Please be certain.
                    </p>
                    <button
                      onClick={handleDeleteAccount}
                      className="flex items-center space-x-2 bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors"
                    >
                      <Trash2 className="h-4 w-4" />
                      <span>Delete Account</span>
                    </button>
                  </div>
                </motion.div>
              )}

              {/* Billing Settings */}
              {activeSection === 'billing' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <div className="bg-white rounded-2xl shadow-sm p-6">
                    <h2 className="text-xl font-semibold text-gray-900 mb-6">Billing Information</h2>
                    <div className="text-center py-12">
                      <CreditCard className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                      <h3 className="text-lg font-medium text-gray-900 mb-2">No billing methods</h3>
                      <p className="text-gray-600 mb-4">Add a payment method to start shopping</p>
                      <button className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Add Payment Method
                      </button>
                    </div>
                  </div>
                </motion.div>
              )}
            </div>
          </div>
        </motion.div>
      </div>
    </div>
  );
};

export default Settings;
