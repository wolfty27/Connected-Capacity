# Current Application Visual Summary

**Purpose:** This document describes the current visual state of the `Connected Capacity` React application for design analysis.
**Context:** The application is a functional Single Page Application (SPA) built with React and Tailwind CSS.

---

## 1. Overall Aesthetic
The application currently resembles a **"Generic Modern Admin Dashboard"**. It uses a clean, light-themed interface relying heavily on default Tailwind CSS utility classes. It lacks specific branding, custom typography, or a defined "Medical/Premium" color system.

*   **Theme**: Light Mode (Global background is `gray-50`).
*   **Vibe**: Functional, utilitarian, unbranded.
*   **Density**: Standard desktop density (comfortable padding).

## 2. Layout Structure
The application uses a standard "Shell" layout common in admin panels:

*   **Sidebar (Left)**:
    *   **Position**: Fixed, always visible on desktop (`w-64`).
    *   **Appearance**: White background (`bg-white`), separated by a thin gray border (`border-r border-gray-200`).
    *   **Behavior**: Responsive (translates off-screen on mobile).
*   **Top Navigation Bar**:
    *   **Position**: Fixed at the top (`pt-20` padding on main content suggests a ~80px height).
    *   **Appearance**: Likely white or light gray (consistent with Sidebar).
*   **Main Content Area**:
    *   **Background**: Very light gray (`bg-gray-50`).
    *   **Padding**: `p-4` (16px) globally.
    *   **Structure**: Fluid width, fills remaining space next to sidebar.

## 3. Visual Language (Design Tokens)

### 3.1 Colors
The app uses **Default Tailwind Colors** exclusively. There is no custom color configuration.
*   **Backgrounds**: `white`, `gray-50`.
*   **Borders**: `gray-200` (Light gray).
*   **Text**:
    *   Primary: `gray-900` (Almost black).
    *   Secondary: `gray-500` (Medium gray).
*   **Brand Colors**: **Missing**. There is no consistent use of Teal or Indigo as seen in the wireframes. Active states likely use standard Blue or Gray.

### 3.2 Typography
*   **Font Family**: System Default (San Francisco on Mac, Segoe UI on Windows). The custom "Inter" font is **not** currently configured.
*   **Headings**: Bold weights (`font-bold`), standard sizing (`text-lg`, `text-xl`).

### 3.3 Shapes & Effects
*   **Corner Radius**: `rounded-lg` (8px) is the standard for cards and inputs.
*   **Shadows**: `shadow-sm` (Subtle) used on Cards.
*   **Borders**: 1px solid `gray-200` is used universally for separation.

## 4. Component Styles

### 4.1 Cards (`Card.jsx`)
The primary content container is a simple white box:
*   **Background**: White.
*   **Border**: 1px Gray-200.
*   **Shadow**: Small (`shadow-sm`).
*   **Header**: A distinct section with a bottom border (`border-b border-gray-200`) and bold text.
*   **Body**: Padded content area (`p-6`).

### 4.2 Navigation
*   **Sidebar Links**: Simple text links.
    *   **Inactive**: Dark gray text (`text-gray-900`), light gray hover (`hover:bg-gray-100`).
    *   **Active**: Blue tint (`bg-blue-100`, `text-blue-700`) - *Note: This deviates from the intended Teal brand.*

## 5. Summary of Gaps (vs. Intended Design)
To align with the intended "Connected Capacity" brand, the following visual changes are needed:
1.  **Color Update**: Replace generic Blue/Gray active states with **Teal-900** and **Teal-50**.
2.  **Typography**: Switch to **Inter** font family.
3.  **Refinement**: Increase border radius to `rounded-xl` (12px) for a softer, modern feel.
4.  **Depth**: Use stronger shadows or "glass" effects for the Top Bar.
