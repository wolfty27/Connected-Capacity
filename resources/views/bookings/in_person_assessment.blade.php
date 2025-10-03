@extends('dashboard.layout')

@push('additional_css')
    @include('datatables.css')
@endpush

@section('dashboard_content')
    <div class="content-page">
        <div class="content">
            <!-- Start Content-->
            <div class="container-fluid">
                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <a class="btn btn-primary" href="{{ url()->previous() }}" >Back</a>
                            </div>
                            <h4 class="page-title">Assesment Form</h4>
                        </div>
                    </div>
                </div>
                <!-- end page title -->
                @if (Session::has('errors'))
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <div>{{ Session::get('errors') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (Session::has('success'))
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <div>{{ Session::get('success') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form>
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <h3>Patient Information</h3>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Full Name</label
                                                        >
                                                        <input
                                                            disabled
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="David"
                                                        />
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="example-email" class="form-label"
                                                        >Gender</label
                                                        >
                                                        <select class="form-select mb-3" disabled>
                                                            <option selected="">Male</option>
                                                            <option value="1">Male</option>
                                                            <option value="2">Female</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Phone Number</label
                                                        >
                                                        <input
                                                            type="tel"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Phone Number"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Email</label
                                                        >
                                                        <input
                                                            type="email"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Email"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <h5>Status</h5>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="searching_for_placement"
                                                            name="status"
                                                            value="Searching for Placement"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="searching_for_placement"
                                                        >Searching for Placement</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="placement_options_presented"
                                                            name="status"
                                                            value="Placement Options Presented"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="placement_options_presented"
                                                        >Placement Options Presented</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="discharged"
                                                            name="status"
                                                            value="Discharged"
                                                            class="form-check-input"
                                                        />
                                                          <label for="discharged">Discharged</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="placement_selected_at"
                                                            name="status"
                                                            value="Placement Selected at"
                                                            class="form-check-input"
                                                        />

                                                        <label for="placement_selected_at"
                                                        >Placement Selected at</label
                                                        >
                                                        <br />
                                                        <label for="simpleinput" class="form-label"
                                                        >Retirement Home Name</label
                                                        >
                                                        <input
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            disabled

                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <h3>Secondary Contact Information</h3>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Full Name</label
                                                        >
                                                        <input
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="First name"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Last Name</label
                                                        >
                                                        <input
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Last name"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Relationship to Patient</label
                                                        >
                                                        <input
                                                            type="text"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Relationship to Patient"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Phone Number</label
                                                        >
                                                        <input
                                                            type="tel"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Phone Number"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <label for="simpleinput" class="form-label"
                                                        >Email</label
                                                        >
                                                        <input
                                                            type="email"
                                                            id="simpleinput"
                                                            class="form-control"
                                                            placeholder="Email"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <h3>Questionnaire</h3>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <p>1. Has this patient been designated ALC?</p>
                                                        <input
                                                            type="radio"
                                                            id="yes"
                                                            name="designated_alc"
                                                            value="yes"
                                                            class="form-check-input"
                                                        />
                                                        <label for="yes">Yes</label>
                                                        <input
                                                            type="radio"
                                                            id="no"
                                                            name="designated_alc"
                                                            value="no"
                                                            class="form-check-input"
                                                        />
                                                        <label for="no">No</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <p>
                                                            2. Has the patient been considered medically
                                                            stable for at least 3 days
                                                        </p>
                                                        <input
                                                            type="radio"
                                                            id="yes"
                                                            name="least_3_days"
                                                            value="yes"
                                                            class="form-check-input"
                                                        />
                                                        <label for="yes">Yes</label>
                                                        <input
                                                            type="radio"
                                                            id="no"
                                                            name="least_3_days"
                                                            value="no"
                                                            class="form-check-input"
                                                        />
                                                        <label for="no">No</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <p>
                                                            3. Does this patient have a negative PCR COVID
                                                            test?
                                                        </p>
                                                        <input
                                                            type="radio"
                                                            id="yes"
                                                            name="pcr_covid_test"
                                                            value="yes"
                                                            class="form-check-input"
                                                        />
                                                        <label for="yes">Yes</label>
                                                        <input
                                                            type="radio"
                                                            id="no"
                                                            name="pcr_covid_test"
                                                            value="no"
                                                            class="form-check-input"
                                                        />
                                                        <label for="no">No</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <p>
                                                            4. Is there a post-acute care destination/plan
                                                            in place?
                                                        </p>
                                                        <input
                                                            type="radio"
                                                            id="yes"
                                                            name="post_acute"
                                                            value="yes"
                                                            class="form-check-input"
                                                        />
                                                        <label for="yes">Yes</label>
                                                        <input
                                                            type="radio"
                                                            id="no"
                                                            name="post_acute"
                                                            value="no"
                                                            class="form-check-input"
                                                        />
                                                        <label for="no">No</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <p>
                                                        5. If Yes to 4, What is the planned post-acute
                                                        care destination?
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="offer_in_place"
                                                            name="if_yes"
                                                            value="offer_in_place"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="offer_in_place"
                                                        >LTC: Offer In-Place</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="program_support"
                                                            name="if_yes"
                                                            value="program_support"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="program_support"
                                                        >Home without Program Support</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="retirement-home"
                                                            name="if_yes"
                                                            value="retirement-home"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="retirement-home"
                                                        >Retirement Home</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="waitlist"
                                                            name="if_yes"
                                                            value="waitlist"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="waitlist">LTC: On Waitlist</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-8">
                                                    <div class="mb-3">
                                                        <input
                                                            type="radio"
                                                            id="home_with_program_Support"
                                                            name="if_yes"
                                                            value="home_with_program_Support"
                                                            class="form-check-input"
                                                        />
                                                         
                                                        <label for="home_with_program_Support"
                                                        >Home with Program Support (ie Hospital at
                                                            Home, Community Care)</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <p>
                                                            6. What is the estimated length of need (in
                                                            days) for post-acute care?
                                                        </p>
                                                        <div>
                                                            <div class="range">
                                                                <input
                                                                    type="range"
                                                                    min="1"
                                                                    max="5"
                                                                    steps="1"
                                                                    value="1"
                                                                />
                                                            </div>

                                                            <ul class="range-labels">
                                                                <li class="active selected">30 Days</li>
                                                                <li>30 - 45 Days</li>
                                                                <li>45 - 60 Days</li>
                                                                <li>60 - 75 Days</li>
                                                                <li>75 - 90 Days</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12 mt-3">
                                                    <p>
                                                        7. What post-acute care needs does the patient
                                                        have? Check all that apply. NPC (Nursing and
                                                        Personal Care) and AR (Activation and
                                                        Recreation) are included.
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="npc1"
                                                            name="npc"
                                                            value="Physical Rehabilitation"
                                                            class="form-check-input"
                                                        />
                                                        <label for="npc1">
                                                            Physical Rehabilitation: Mild to Moderate
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="npc2"
                                                            name="npc"
                                                            value="Behavioural Support"
                                                            class="form-check-input"
                                                        />
                                                        <label for="npc2"> Behavioural Support </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="npc3"
                                                            name="npc"
                                                            value="Physical Rehabilitation Advanced"
                                                            class="form-check-input"
                                                        />
                                                        <label for="npc3">
                                                            Physical Rehabilitation: Advanced
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="npc4"
                                                            name="npc"
                                                            value="Caregiver Support"
                                                            class="form-check-input"
                                                        />
                                                        <label for="npc4"> Caregiver Support</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12 mt-3">
                                                    <p>
                                                        8. Acceptable Patient Characteristics (Check all
                                                        that apply)
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc1"
                                                            name="apc"
                                                            value="Non-weight bearing"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc1"> Non-weight bearing</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc2"
                                                            name="apc"
                                                            value="Mobility Restricted/Bedrest"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc2"
                                                        >Mobility Restricted/Bedrest
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc3"
                                                            name="apc"
                                                            value="Requires 2+ person transfer"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc3"
                                                        >Requires 2+ person transfer</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc4"
                                                            name="apc"
                                                            value="Caregiver experiencing burden or burnout"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc4">
                                                            Caregiver experiencing burden or
                                                            burnout</label
                                                        >
                                                    </div>
                                                </div>

                                                <div class="col-lg-8">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc5"
                                                            name="apc"
                                                            value="Early to moderate"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc5"
                                                        >Early to moderate wound care (dressings 30
                                                            minutes or less)</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc6"
                                                            name="apc"
                                                            value="Bedside oxygen"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc6"> Bedside oxygen</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc7"
                                                            name="apc"
                                                            value="PICC line already inserted"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc7"
                                                        >PICC line already inserted
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc8"
                                                            name="apc"
                                                            value="Stable IV for antibiotics
                                  and other therapies"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc8"
                                                        >Stable IV for antibiotics and other
                                                            therapies</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="apc9"
                                                            name="apc"
                                                            value="Mild to moderate"
                                                            class="form-check-input"
                                                        />
                                                        <label for="apc9"
                                                        >Mild to moderate cognitive impairment such as
                                                            Dementia, resolving delirium, behavioural
                                                            supports with behaviour plan in place
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12 mt-3">
                                                    <p>
                                                        9. You confirm that, to the best of your
                                                        knowledge, the patient does not have ANY of the
                                                        below characteristics. (Check any that the
                                                        patient has)
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk1"
                                                            name="bk"
                                                            value="Has high elopement risk"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk1">
                                                            Has high elopement risk</label
                                                        >
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk2"
                                                            name="bk"
                                                            value="Exhibits physically responsive behaviours"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk2"
                                                        >Exhibits physically responsive behaviours
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk3"
                                                            name="bk"
                                                            value="Requires acute palliative
                                  care"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk3"
                                                        >Requires acute palliative care</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk4"
                                                            name="bk"
                                                            value="Is fully immobilized/
                                  in traction"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk4">
                                                            Is fully immobilized/ in traction</label
                                                        >
                                                    </div>
                                                </div>

                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk5"
                                                            name="bk"
                                                            value="Requires administration of blood products"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk5"
                                                        >Requires administration of blood
                                                            products</label
                                                        >
                                                    </div>
                                                </div>

                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk6"
                                                            name="bk"
                                                            value="Has Active TB / CDiff /
                                COVID positive"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk6">
                                                            Has Active TB / CDiff / COVID positive</label
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">

                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <input
                                                            type="checkbox"
                                                            id="bk7"
                                                            name="bk"
                                                            value="Has acute medical"
                                                            class="form-check-input"
                                                        />
                                                        <label for="bk7"
                                                        >Has acute medical needs, e.g. trach,
                                                            suctioning, aerosol generating procedures,
                                                            ventilator, etc.
                                                        </label>
                                                    </div>
                                                </div>

                                            </div>

                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <div class="col-lg-4">
                                                            <div class="mb-3">
                                                                <h3>Select Tier</h3>
                                                                <select class="form-select mb-3">
                                                                    <option selected="">Select Tier</option>
                                                                    <option value="1">Tier 1</option>
                                                                    <option value="2">Tier 2</option>
                                                                    <option value="3">Tier 3</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <h3>Upload Documents</h3>
                                                        <!-- <label
                                                          for="example-fileinput"
                                                          class="form-label"
                                                          >Upload Documents</label
                                                        > -->
                                                        <input
                                                            type="file"
                                                            id="example-fileinput"
                                                            class="form-control"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <h4>Required</h4>
                                                        <ul class="user_documents">
                                                            <li><img src="assets/images/svg/document_icons/attach_file_add.png" width="20px"/> Hospital Notes/Chart</li>
                                                            <li><img src="assets/images/svg/document_icons/attach_file_add.png" width="20px"/>  Medical History</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <h4>Optional</h4>
                                                        <ul class="user_documents">
                                                            <li><img src="assets/images/svg/document_icons/cloud_done.png" width="20px"/> Rehab Plan </li>
                                                            <li><img src="assets/images/svg/document_icons/cloud_done.png" width="20px"/>  Dietary Needs</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>

                                            <button class="btn btn-primary" type="submit">
                                                Submit
                                            </button>
                                        </div>
                                        <!-- end col -->
                                    </div>
                                    <!-- end row-->
                                </form>
                            </div>
                            <!-- end card-body -->
                        </div>
                        <!-- end card -->
                    </div>
                    <!-- end col -->
                </div>
                <!-- end row -->
            </div>
            <!-- container -->
        </div>
        <!-- content -->

        <!-- Footer Start -->
        {{-- <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6">
                        <script>
                            document.write(new Date().getFullYear());
                        </script>
                        © Connected Capacity
                    </div>
                    <div class="col-md-6">
                        <div class="text-md-end footer-links d-none d-md-block">
                            <a href="javascript: void(0);">About</a>
                            <a href="javascript: void(0);">Support</a>
                            <a href="javascript: void(0);">Contact Us</a>
                        </div>
                    </div>
                </div>
            </div>
        </footer> --}}
        <!-- end Footer -->
    </div>
@endsection

@push('additional_scripts')
    @include('components.progress_bar_script')
@endpush
