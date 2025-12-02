import React, { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { motion } from 'framer-motion';
import { 
  User, 
  Mail, 
  Phone, 
  MapPin, 
  Calendar,
  Edit3,
  ShoppingBag,
  Heart,
  Star,
  Package,
  Truck,
  CheckCircle,
  XCircle
} from 'lucide-react';
import { toast } from 'react-hot-toast';

const Profile = () => {
  const { user } = useSelector((state) => state.auth);
  const [activeTab, setActiveTab] = useState('overview');
  const [isEditing, setIsEditing] = useState(false);
  const [profileData, setProfileData] = useState({
    name: '',
    email: '',
    phone: '',
    address: '',
    bio: ''
  });

  // Mock user data
  const userData = {
    id: 1,
    name: 'John Doe',
    email: 'john.doe@example.com',
    phone: '+1 (555) 123-4567',
    address: '123 Main St, New York, NY 10001',
    joinDate: '2023-01-15',
    avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150&h=150&fit=crop&crop=face',
    bio: 'Tech enthusiast and avid online shopper. Love discovering new products and great deals!'
  };

  // Mock orders data
  const orders = [
    {
      id: 'ORD-001',
      date: '2024-01-15',
      total: 149.99,
      status: 'delivered',
      items: 3,
      tracking: 'TRK123456789'
    },
    {
      id: 'ORD-002',
      date: '2024-01-10',
      total: 89.99,
      status: 'shipped',
      items: 2,
      tracking: 'TRK123456788'
    },
    {
      id: 'ORD-003',
      date: '2024-01-05',
      total: 299.99,
      status: 'processing',
      items: 1,
      tracking: 'TRK123456787'
    }
  ];

  // Mock wishlist data
  const wishlist = [
    {
      id: 1,
      name: 'Wireless Earbuds',
      price: 79.99,
      image: 'https://images.unsplash.com/photo-1590658165737-15a047b8b5e0?w=100',
      rating: 4.5
    },
    {
      id: 2,
      name: 'Smart Watch',
      price: 199.99,
      image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=100',
      rating: 4.8
    },
    {
      id: 3,
      name: 'Laptop Stand',
      price: 49.99,
      image: 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=100',
      rating: 4.3
    }
  ];

  useEffect(() => {
    setProfileData({
      name: userData.name,
      email: userData.email,
      phone: userData.phone,
      address: userData.address,
      bio: userData.bio
    });
  }, []);

  const handleSaveProfile = () => {
    // Validate required fields
    if (!profileData.name.trim() || !profileData.email.trim()) {
      toast.error('Name and email are required');
      return;
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(profileData.email)) {
      toast.error('Please enter a valid email address');
      return;
    }

    // Phone validation (if provided)
    if (profileData.phone && !/^\+?[\d\s-()]+$/.test(profileData.phone)) {
      toast.error('Please enter a valid phone number');
      return;
    }

    // Simulate API call
    toast.success('Profile updated successfully!');
    setIsEditing(false);
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'delivered': return 'text-green-600 bg-green-100';
      case 'shipped': return 'text-blue-600 bg-blue-100';
      case 'processing': return 'text-yellow-600 bg-yellow-100';
      default: return 'text-gray-600 bg-gray-100';
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'delivered': return <CheckCircle className="h-4 w-4" />;
      case 'shipped': return <Truck className="h-4 w-4" />;
      case 'processing': return <Package className="h-4 w-4" />;
      default: return <XCircle className="h-4 w-4" />;
    }
  };

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
            <div className="flex flex-col md:flex-row items-start md:items-center space-y-4 md:space-y-0 md:space-x-6">
              <div className="relative">
                <img
                  src={userData.avatar}
                  alt={userData.name}
                  className="w-20 h-20 rounded-full object-cover border-4 border-blue-100"
                />
                <button className="absolute bottom-0 right-0 bg-blue-600 text-white p-1.5 rounded-full hover:bg-blue-700 transition-colors">
                  <Edit3 className="h-3 w-3" />
                </button>
              </div>
              <div className="flex-1">
                <h1 className="text-2xl font-bold text-gray-900">{userData.name}</h1>
                <p className="text-gray-600 mt-1">{userData.bio}</p>
                <div className="flex flex-wrap items-center gap-4 mt-3 text-sm text-gray-500">
                  <div className="flex items-center space-x-1">
                    <Mail className="h-4 w-4" />
                    <span>{userData.email}</span>
                  </div>
                  <div className="flex items-center space-x-1">
                    <Calendar className="h-4 w-4" />
                    <span>Joined {new Date(userData.joinDate).toLocaleDateString()}</span>
                  </div>
                </div>
              </div>
              <button
                onClick={() => setIsEditing(!isEditing)}
                className="flex items-center space-x-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
              >
                <Edit3 className="h-4 w-4" />
                <span>{isEditing ? 'Cancel Editing' : 'Edit Profile'}</span>
              </button>
            </div>
          </div>

          {/* Main Content */}
          <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
            {/* Sidebar */}
            <div className="lg:col-span-1">
              <div className="bg-white rounded-2xl shadow-sm p-6 space-y-2 sticky top-8">
                {[
                  { id: 'overview', label: 'Overview', icon: User },
                  { id: 'orders', label: 'My Orders', icon: ShoppingBag },
                  { id: 'wishlist', label: 'Wishlist', icon: Heart },
                  { id: 'reviews', label: 'My Reviews', icon: Star }
                ].map((tab) => (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors ${
                      activeTab === tab.id
                        ? 'bg-blue-50 text-blue-700 border-r-2 border-blue-700'
                        : 'text-gray-600 hover:bg-gray-50'
                    }`}
                  >
                    <tab.icon className="h-5 w-5" />
                    <span className="font-medium">{tab.label}</span>
                  </button>
                ))}
              </div>
            </div>

            {/* Content Area */}
            <div className="lg:col-span-3 space-y-6">
              {/* Overview Tab */}
              {activeTab === 'overview' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  {/* Profile Information */}
                  <div className="bg-white rounded-2xl shadow-sm p-6">
                    <h2 className="text-xl font-semibold text-gray-900 mb-6">
                      Personal Information
                    </h2>
                    
                    {isEditing ? (
                      <div className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                              Full Name *
                            </label>
                            <input
                              type="text"
                              value={profileData.name}
                              onChange={(e) => setProfileData({...profileData, name: e.target.value})}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter your full name"
                            />
                          </div>
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                              Email Address *
                            </label>
                            <input
                              type="email"
                              value={profileData.email}
                              onChange={(e) => setProfileData({...profileData, email: e.target.value})}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter your email"
                            />
                          </div>
                        </div>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                              Phone Number
                            </label>
                            <input
                              type="tel"
                              value={profileData.phone}
                              onChange={(e) => setProfileData({...profileData, phone: e.target.value})}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter your phone number"
                            />
                          </div>
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                              Address
                            </label>
                            <input
                              type="text"
                              value={profileData.address}
                              onChange={(e) => setProfileData({...profileData, address: e.target.value})}
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter your address"
                            />
                          </div>
                        </div>
                        
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">
                            Bio
                          </label>
                          <textarea
                            value={profileData.bio}
                            onChange={(e) => setProfileData({...profileData, bio: e.target.value})}
                            rows="3"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Tell us about yourself..."
                          />
                        </div>
                        
                        <div className="flex space-x-3 pt-4">
                          <button
                            onClick={handleSaveProfile}
                            className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                          >
                            Save Changes
                          </button>
                          <button
                            onClick={() => setIsEditing(false)}
                            className="bg-gray-200 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-300 transition-colors"
                          >
                            Cancel
                          </button>
                        </div>
                      </div>
                    ) : (
                      <div className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                          <div className="flex items-center space-x-3 p-4 bg-gray-50 rounded-lg">
                            <User className="h-5 w-5 text-blue-600" />
                            <div>
                              <p className="text-sm text-gray-600">Full Name</p>
                              <p className="font-medium text-gray-900">{profileData.name}</p>
                            </div>
                          </div>
                          
                          <div className="flex items-center space-x-3 p-4 bg-gray-50 rounded-lg">
                            <Mail className="h-5 w-5 text-blue-600" />
                            <div>
                              <p className="text-sm text-gray-600">Email Address</p>
                              <p className="font-medium text-gray-900">{profileData.email}</p>
                            </div>
                          </div>
                          
                          <div className="flex items-center space-x-3 p-4 bg-gray-50 rounded-lg">
                            <Phone className="h-5 w-5 text-blue-600" />
                            <div>
                              <p className="text-sm text-gray-600">Phone Number</p>
                              <p className="font-medium text-gray-900">
                                {profileData.phone || 'Not provided'}
                              </p>
                            </div>
                          </div>
                          
                          <div className="flex items-center space-x-3 p-4 bg-gray-50 rounded-lg">
                            <MapPin className="h-5 w-5 text-blue-600" />
                            <div>
                              <p className="text-sm text-gray-600">Address</p>
                              <p className="font-medium text-gray-900">
                                {profileData.address || 'Not provided'}
                              </p>
                            </div>
                          </div>
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Quick Stats */}
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="bg-white rounded-2xl shadow-sm p-6 text-center">
                      <ShoppingBag className="h-8 w-8 text-blue-600 mx-auto mb-3" />
                      <h3 className="text-2xl font-bold text-gray-900">{orders.length}</h3>
                      <p className="text-gray-600">Total Orders</p>
                    </div>
                    
                    <div className="bg-white rounded-2xl shadow-sm p-6 text-center">
                      <Heart className="h-8 w-8 text-red-600 mx-auto mb-3" />
                      <h3 className="text-2xl font-bold text-gray-900">{wishlist.length}</h3>
                      <p className="text-gray-600">Wishlist Items</p>
                    </div>
                    
                    <div className="bg-white rounded-2xl shadow-sm p-6 text-center">
                      <Star className="h-8 w-8 text-yellow-600 mx-auto mb-3" />
                      <h3 className="text-2xl font-bold text-gray-900">12</h3>
                      <p className="text-gray-600">Reviews Written</p>
                    </div>
                  </div>
                </motion.div>
              )}

              {/* Orders Tab */}
              {activeTab === 'orders' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <div className="bg-white rounded-2xl shadow-sm p-6">
                    <h2 className="text-xl font-semibold text-gray-900 mb-6">Order History</h2>
                    
                    <div className="space-y-4">
                      {orders.map((order) => (
                        <div key={order.id} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                          <div className="flex flex-col md:flex-row md:items-center justify-between space-y-3 md:space-y-0">
                            <div className="space-y-2">
                              <div className="flex items-center space-x-4">
                                <span className="font-semibold text-gray-900">{order.id}</span>
                                <span className={`px-3 py-1 rounded-full text-xs font-medium flex items-center space-x-1 ${getStatusColor(order.status)}`}>
                                  {getStatusIcon(order.status)}
                                  <span className="capitalize">{order.status}</span>
                                </span>
                              </div>
                              <div className="text-sm text-gray-600 space-y-1">
                                <p>Placed on {new Date(order.date).toLocaleDateString()}</p>
                                <p>{order.items} item(s) • ${order.total}</p>
                                {order.tracking && (
                                  <p className="text-blue-600">Tracking: {order.tracking}</p>
                                )}
                              </div>
                            </div>
                            
                            <div className="flex space-x-2">
                              <button className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                                View Details
                              </button>
                              {order.status === 'delivered' && (
                                <button className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                                  Buy Again
                                </button>
                              )}
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </motion.div>
              )}

              {/* Wishlist Tab */}
              {activeTab === 'wishlist' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <div className="bg-white rounded-2xl shadow-sm p-6">
                    <h2 className="text-xl font-semibold text-gray-900 mb-6">My Wishlist</h2>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                      {wishlist.map((item) => (
                        <div key={item.id} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                          <div className="flex space-x-4">
                            <img
                              src={item.image}
                              alt={item.name}
                              className="w-16 h-16 object-cover rounded-lg"
                            />
                            <div className="flex-1">
                              <h3 className="font-medium text-gray-900 mb-1">{item.name}</h3>
                              <p className="text-lg font-semibold text-blue-600 mb-2">${item.price}</p>
                              <div className="flex items-center justify-between">
                                <div className="flex items-center space-x-1">
                                  <Star className="h-4 w-4 text-yellow-400 fill-current" />
                                  <span className="text-sm text-gray-600">{item.rating}</span>
                                </div>
                                <button className="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition-colors">
                                  Add to Cart
                                </button>
                              </div>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </motion.div>
              )}

              {/* Reviews Tab */}
              {activeTab === 'reviews' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <div className="bg-white rounded-2xl shadow-sm p-6">
                    <h2 className="text-xl font-semibold text-gray-900 mb-6">My Reviews</h2>
                    <div className="text-center py-12">
                      <Star className="h-12 w-12 text-gray-300 mx-auto mb-4" />
                      <h3 className="text-lg font-medium text-gray-900 mb-2">No reviews yet</h3>
                      <p className="text-gray-600 mb-4">Start shopping and share your experience with others!</p>
                      <button className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Start Shopping
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

export default Profile;
