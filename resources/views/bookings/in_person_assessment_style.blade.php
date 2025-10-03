<style>
    .range {
        position: relative;
        width: 80%;
        height: 5px;
    }
    .range input {
        width: 100%;
        position: absolute;
        top: 2px;
        height: 0;
        -webkit-appearance: none;
    }
    .range input::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 14px;
        height: 14px;
        margin: -3px 0 0;
        border-radius: 50%;
        background: #536de6;
        cursor: pointer;
        border: 0 !important;
    }
    .range input::-moz-range-thumb {
        width: 14px;
        height: 14px;
        margin: -3px 0 0;
        border-radius: 50%;
        background: #536de6;
        cursor: pointer;
        border: 0 !important;
    }
    .range input::-ms-thumb {
        width: 14px;
        height: 14px;
        margin: -3px 0 0;
        border-radius: 50%;
        background: #536de6;
        cursor: pointer;
        border: 0 !important;
    }
    .range input::-webkit-slider-runnable-track {
        width: 80%;
        height: 8px;
        cursor: pointer;
        background: #b2b2b2;
        border-radius: 3px;
    }
    .range input::-moz-range-track {
        width: 80%;
        height: 8px;
        cursor: pointer;
        background: #b2b2b2;
        border-radius: 3px;
    }
    .range input::-ms-track {
        width: 80%;
        height: 8px;
        cursor: pointer;
        background: #b2b2b2;
        border-radius: 3px;
    }
    .range input:focus {
        background: none;
        outline: none;
    }
    .range input::-ms-track {
        width: 80%;
        cursor: pointer;
        background: transparent;
        border-color: transparent;
        color: transparent;
    }
    .range-labels {
        margin: 18px -41px 0;
        padding: 0;
        list-style: none;
    }
    .range-labels li {
        position: relative;
        float: left;
        width: 17%;
        text-align: center;
        color: #b2b2b2;
        font-size: 14px;
        cursor: pointer;
    }
    .range-labels .active {
        color: #536de6;
    }
    .range-labels .selected::before {
        background: #536de6;
    }
    .range-labels .active.selected::before {
        display: none;
    }
    .user_documents li{
        list-style: none;
    }
    .user_documents{
        padding-left: 0px;
    }
</style>
