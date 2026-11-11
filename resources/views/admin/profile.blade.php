@extends('layouts.app')
@section('content')
   <div class="page-content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-xxl-3">
            <div class="card mt-n5">
               <div class="card-body p-4">
                 <div class="text-center">
                   <div class="profile-user position-relative d-inline-block mx-auto mb-4">
                     <img src="assets/images/users/avatar-1.jpg"
                        class="rounded-circle avatar-xl img-thumbnail user-profile-image material-shadow"
                        alt="user-profile-image">
                     <div class="avatar-xs p-0 rounded-circle profile-photo-edit">
                        <input id="profile-img-file-input" type="file" class="profile-img-file-input">
                        <label for="profile-img-file-input" class="profile-photo-edit avatar-xs">
                          <span class="avatar-title rounded-circle bg-light text-body material-shadow">
                            <i class="ri-camera-fill"></i>
                          </span>
                        </label>
                     </div>
                   </div>
                   <h5 class="fs-16 mb-1">{{ $user_detail->name }}</h5>
                   <p class="text-muted mb-0"> {{ Auth::user()->getRoleNames()->implode(', ') }}</p>
                 </div>
               </div>
            </div>
          </div>
          <div class="col-xxl-9">
            <div class="card mt-xxl-n5">
               <div class="card-header">
                 <ul class="nav nav-tabs-custom rounded card-header-tabs border-bottom-0" role="tablist">
                   <li class="nav-item">
                     <a class="nav-link active" data-bs-toggle="tab" href="#personalDetails" role="tab">
                        <i class="fas fa-home"></i> Personal Details
                     </a>
                   </li>
                   <li class="nav-item">
                     <a class="nav-link" data-bs-toggle="tab" href="#changePassword" role="tab">
                        <i class="far fa-user"></i> Change Password
                     </a>
                   </li>
                 </ul>
               </div>
               <div class="card-body p-4">
                 <div class="tab-content">
                   <div class="tab-pane active" id="personalDetails" role="tabpanel">
                     <form action="javascript:void(0);">
                        <div class="row">
                          <div class="col-lg-6">
                            <div class="mb-3">
                              <label for="firstnameInput" class="form-label">First Name</label>
                              <input type="text" class="form-control" id="firstnameInput"
                                 placeholder="Enter your firstname"
                                 value="{{ explode(' ', $user_detail->name)[0] }}">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="mb-3">
                              <label for="lastnameInput" class="form-label">Last Name</label>
                              <input type="text" class="form-control" id="lastnameInput"
                                 placeholder="Enter your lastname"
                                 value="{{ implode(' ', array_slice(explode(' ', $user_detail->name), 1)) }}">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="mb-3">
                              <label for="phonenumberInput" class="form-label">Phone Number</label>
                              <input type="text" class="form-control" id="phonenumberInput"
                                 placeholder="Enter your phone number" value="-">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="mb-3">
                              <label for="emailInput" class="form-label">Email Address</label>
                              <input type="email" class="form-control" id="emailInput"
                                 placeholder="Enter your email" value="{{ $user_detail->email }}">
                            </div>
                          </div>
                          <div class="col-lg-12">
                            <div class="mb-3">
                              <label for="JoiningdatInput" class="form-label">Joining Date</label>
                              <input type="text" class="form-control" data-provider="flatpickr"
                                 id="JoiningdatInput" data-date-format="d M, Y"
                                 data-deafult-date="{{ $user_detail->created_at->format('d M, Y') }}"
                                 placeholder="Select date"
                                 value="{{ $user_detail->created_at->format('d M, Y') }}" />
                            </div>
                          </div>
                          <div class="col-lg-12">
                            <div class="mb-3">
                              <label for="skillsInput" class="form-label">Skills</label>
                              <select class="form-control" name="skillsInput" data-choices
                                 data-choices-text-unique-true multiple id="skillsInput">
                                 <option value="illustrator">Illustrator</option>
                                 <option value="photoshop">Photoshop</option>
                                 <option value="css">CSS</option>
                                 <option value="html">HTML</option>
                                 <option value="javascript">Javascript</option>
                                 <option value="python">Python</option>
                                 <option value="php">PHP</option>
                              </select>
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="mb-3">
                              <label for="designationInput" class="form-label">Designation</label>
                              <input type="text" class="form-control" id="designationInput"
                                 placeholder="Designation" value="User">
                            </div>
                          </div>
                          <div class="col-lg-6">
                            <div class="mb-3">
                              <label for="websiteInput1" class="form-label">Website</label>
                              <input type="text" class="form-control" id="websiteInput1"
                                 placeholder="www.example.com" value="-" />
                            </div>
                          </div>
                          <div class="col-lg-4">
                            <div class="mb-3">
                              <label for="cityInput" class="form-label">City</label>
                              <input type="text" class="form-control" id="cityInput" placeholder="City"
                                 value="-" />
                            </div>
                          </div>
                          <div class="col-lg-4">
                            <div class="mb-3">
                              <label for="countryInput" class="form-label">Country</label>
                              <input type="text" class="form-control" id="countryInput" placeholder="Country"
                                 value="-" />
                            </div>
                          </div>
                          <div class="col-lg-4">
                            <div class="mb-3">
                              <label for="zipcodeInput" class="form-label">Zip Code</label>
                              <input type="text" class="form-control" minlength="5" maxlength="6"
                                 id="zipcodeInput" placeholder="Enter zipcode" value="-">
                            </div>
                          </div>
                          <div class="col-lg-12">
                            <div class="mb-3 pb-2">
                              <label for="exampleFormControlTextarea" class="form-label">Description</label>
                              <textarea class="form-control" id="exampleFormControlTextarea"
                                 placeholder="Enter your description" rows="3">-</textarea>
                            </div>
                          </div>
                          <div class="col-lg-12">
                            <div class="hstack gap-2 justify-content-end">
                              <button type="submit" class="btn btn-primary">Updates</button>
                              <button type="button" class="btn btn-soft-success">Cancel</button>
                            </div>
                          </div>
                        </div>
                     </form>
                   </div>
                   <div class="tab-pane" id="changePassword" role="tabpanel">
                     <form action="javascript:void(0);">
                        <div class="row g-2">
                          <div class="col-lg-4">
                            <div>
                              <label for="oldpasswordInput" class="form-label">Old Password*</label>
                              <input type="password" class="form-control" id="oldpasswordInput"
                                 placeholder="Enter current password">
                            </div>
                          </div>
                          <div class="col-lg-4">
                            <div>
                              <label for="newpasswordInput" class="form-label">New Password*</label>
                              <input type="password" class="form-control" id="newpasswordInput"
                                 placeholder="Enter new password">
                            </div>
                          </div>
                          <div class="col-lg-4">
                            <div>
                              <label for="confirmpasswordInput" class="form-label">Confirm
                                 Password*</label>
                              <input type="password" class="form-control" id="confirmpasswordInput"
                                 placeholder="Confirm password">
                            </div>
                          </div>
                          <div class="col-lg-12">
                            <div class="mb-3">
                              <a href="javascript:void(0);" class="link-primary text-decoration-underline">Forgot
                                 Password
                                 ?</a>
                            </div>
                          </div>
                          <div class="col-lg-12">
                            <div class="text-end">
                              <button type="submit" class="btn btn-success">Change
                                 Password</button>
                            </div>
                          </div>
                        </div>
                     </form>
                     <div class="mt-4 mb-3 border-bottom pb-2">
                        <div class="float-end">
                          <a href="javascript:void(0);" class="link-primary">All Logout</a>
                        </div>
                        <h5 class="card-title">Login History</h5>
                     </div>
                     <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 avatar-sm">
                          <div class="avatar-title bg-light text-primary rounded-3 fs-18 material-shadow">
                            <i class="ri-smartphone-line"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                          <h6>iPhone 12 Pro</h6>
                          <p class="text-muted mb-0">Los Angeles, United States - March 16 at 2:47PM</p>
                        </div>
                        <div>
                          <a href="javascript:void(0);">Logout</a>
                        </div>
                     </div>
                     <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 avatar-sm">
                          <div class="avatar-title bg-light text-primary rounded-3 fs-18 material-shadow">
                            <i class="ri-tablet-line"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                          <h6>Apple iPad Pro</h6>
                          <p class="text-muted mb-0">Washington, United States - November 06 at 10:43AM
                          </p>
                        </div>
                        <div>
                          <a href="javascript:void(0);">Logout</a>
                        </div>
                     </div>
                     <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 avatar-sm">
                          <div class="avatar-title bg-light text-primary rounded-3 fs-18 material-shadow">
                            <i class="ri-smartphone-line"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                          <h6>Galaxy S21 Ultra 5G</h6>
                          <p class="text-muted mb-0">Conneticut, United States - June 12 at 3:24PM</p>
                        </div>
                        <div>
                          <a href="javascript:void(0);">Logout</a>
                        </div>
                     </div>
                     <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 avatar-sm">
                          <div class="avatar-title bg-light text-primary rounded-3 fs-18 material-shadow">
                            <i class="ri-macbook-line"></i>
                          </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                          <h6>Dell Inspiron 14</h6>
                          <p class="text-muted mb-0">Phoenix, United States - July 26 at 8:10AM</p>
                        </div>
                        <div>
                          <a href="javascript:void(0);">Logout</a>
                        </div>
                     </div>
                   </div>
                 </div>
               </div>
            </div>
          </div>
        </div>
      </div>
   </div>
@endsection
@push('scripts')
@endpush