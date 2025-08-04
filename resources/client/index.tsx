import React from 'react';
import ReactDOM from 'react-dom';
import UploadComponent from './uploads/UploadComponent';
import './uploads/upload-component.css';

const App = () => {
    return (
        <div className="upload-page">
            <h1>Welcome to Drime Transfer</h1>
            <UploadComponent />
        </div>
    );
};

ReactDOM.render(<App />, document.getElementById('root'));

