import { createSlice, createAsyncThunk } from "@reduxjs/toolkit";
import axios from "axios";

const API_URL =
  import.meta.env.MODE === "development"
    ? "http://localhost:4000/auth"
    : "/auth";

axios.defaults.withCredentials = true;

// ─────────────────────────────────────────────
// ASYNC THUNKS
// ─────────────────────────────────────────────

export const signup = createAsyncThunk(
  "auth/signup",
  async ({ fullname, username, email, password }, { rejectWithValue }) => {
    try {
      const res = await axios.post(`${API_URL}/signup`, { 
        fullname, 
        username, 
        email, 
        password 
      });
      return res.data.user;
    } catch (err) {
      return rejectWithValue(err.response?.data?.message || "Signup failed");
    }
  }
);

export const signin = createAsyncThunk(
  "auth/signin",
  async ({ email, password }, { rejectWithValue }) => {
    try {
      const res = await axios.post(`${API_URL}/signin`, { email, password });
      return res.data.user;
    } catch (err) {
      return rejectWithValue(err.response?.data?.message || "Signin failed");
    }
  }
);

export const signout = createAsyncThunk(
  "auth/signout",
  async (_, { rejectWithValue }) => {
    try {
      await axios.post(`${API_URL}/signout`);
      return true;
    } catch (err) {
      return rejectWithValue(err.response?.data?.message || "Logout failed");
    }
  }
);

export const verifyEmail = createAsyncThunk(
  "auth/verifyEmail",
  async (code, { rejectWithValue }) => {
    try {
      const res = await axios.post(`${API_URL}/verify-email`, { code });
      return res.data.user;
    } catch (err) {
      return rejectWithValue(err.response?.data?.message || "Email verify failed");
    }
  }
);

export const checkAuth = createAsyncThunk(
  "auth/checkAuth",
  async (_, { rejectWithValue }) => {
    try {
      const res = await axios.get(`${API_URL}/check-auth`);
      return res.data.user;
    } catch {
      return rejectWithValue(null); // silent fail
    }
  }
);

export const forgotPassword = createAsyncThunk(
  "auth/forgotPassword",
  async (email, { rejectWithValue }) => {
    try {
      const res = await axios.post(`${API_URL}/forgot-password`, { email });
      return res.data.message;
    } catch (err) {
      return rejectWithValue(err.response?.data?.message || "Forgot password failed");
    }
  }
);

export const resetPassword = createAsyncThunk(
  "auth/resetPassword",
  async ({ token, password }, { rejectWithValue }) => {
    try {
      const res = await axios.post(`${API_URL}/reset-password/${token}`, { password });
      return res.data.message;
    } catch (err) {
      return rejectWithValue(err.response?.data?.message || "Reset password failed");
    }
  }
);

// ─────────────────────────────────────────────
// SLICE
// ─────────────────────────────────────────────

const authSlice = createSlice({
  name: "auth",
  initialState: {
    user: null,
    auth: false,
    checkAuth: true,
    resError: null,
    res: null,
  },
  reducers: {},
  extraReducers: (builder) => {
    builder
      // -------------------------------
      // SIGNUP
      // -------------------------------
      .addCase(signup.fulfilled, (state, action) => {
        state.user = action.payload;
        state.auth = true;
        state.resError = null;
      })
      .addCase(signup.rejected, (state, action) => {
        state.resError = action.payload;
      })

      // -------------------------------
      // SIGNIN
      // -------------------------------
      .addCase(signin.fulfilled, (state, action) => {
        state.user = action.payload;
        state.auth = true;
        state.resError = null;
      })
      .addCase(signin.rejected, (state, action) => {
        state.resError = action.payload;
      })

      // -------------------------------
      // SIGNOUT
      // -------------------------------
      .addCase(signout.fulfilled, (state) => {
        state.user = null;
        state.auth = false;
      })
      .addCase(signout.rejected, (state, action) => {
        state.resError = action.payload;
      })

      // -------------------------------
      // VERIFY EMAIL
      // -------------------------------
      .addCase(verifyEmail.fulfilled, (state, action) => {
        state.user = action.payload;
        state.auth = true;
      })
      .addCase(verifyEmail.rejected, (state, action) => {
        state.resError = action.payload;
      })

      // -------------------------------
      // CHECK AUTH
      // -------------------------------
      .addCase(checkAuth.fulfilled, (state, action) => {
        state.user = action.payload;
        state.auth = true;
        state.checkAuth = false;
      })
      .addCase(checkAuth.rejected, (state) => {
        state.checkAuth = false;
        state.auth = false;
      })

      // -------------------------------
      // FORGOT PASSWORD
      // -------------------------------
      .addCase(forgotPassword.fulfilled, (state, action) => {
        state.res = action.payload;
      })
      .addCase(forgotPassword.rejected, (state, action) => {
        state.resError = action.payload;
      })

      // -------------------------------
      // RESET PASSWORD
      // -------------------------------
      .addCase(resetPassword.fulfilled, (state, action) => {
        state.res = action.payload;
      })
      .addCase(resetPassword.rejected, (state, action) => {
        state.resError = action.payload;
      });
  },
});

export default authSlice.reducer;
