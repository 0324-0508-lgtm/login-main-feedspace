#!/usr/bin/env python3
"""
Feedspace Toxic Detector - Uses YOUR labeled_data.csv
"""

import pandas as pd
import joblib
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score
import re

print("🚀 Feedspace Toxic Detector - labeled_data.csv")

class ToxicDetector:
    def __init__(self):
        self.vectorizer = TfidfVectorizer(max_features=5000, stop_words='english')
        self.model = LogisticRegression(max_iter=1000)
    
    def preprocess(self, text):
        text = str(text).lower()
        text = re.sub(r'http\S+|www\S+|https\S+', '', text)
        text = re.sub(r'@\w+|#\w+', '', text)
        text = re.sub(r'[^a-zA-Z\s]', '', text)
        text = re.sub(r'\s+', ' ', text).strip()
        return text
    
    def train(self, csv_file="labeled_data.csv"):
        print(f"📂 Loading {csv_file}...")
        df = pd.read_csv(csv_file)
        
        # Auto-detect columns (common names)
        text_col = None
        label_col = None
        
        for col in df.columns:
            if 'text' in col.lower() or 'tweet' in col.lower() or 'content' in col.lower():
                text_col = col
            if 'label' in col.lower() or 'class' in col.lower() or 'target' in col.lower():
                label_col = col
        
        if text_col is None:
            print("❌ No text column found! Columns:", list(df.columns))
            return False
            
        if label_col is None:
            print("❌ No label column found! Columns:", list(df.columns))
            return False
        
        print(f"✅ Using: {text_col} → {label_col}")
        
        # Preprocess
        df['clean_text'] = df[text_col].apply(self.preprocess)
        X = df['clean_text'].fillna('')
        y = df[label_col]
        
        print(f"📊 Dataset: {len(df)} rows, Toxic: {y.sum()}, Safe: {len(y)-y.sum()}")
        
        # Vectorize & split
        X_vec = self.vectorizer.fit_transform(X)
        X_train, X_test, y_train, y_test = train_test_split(
            X_vec, y, test_size=0.2, random_state=42, stratify=y
        )
        
        # Train
        self.model.fit(X_train, y_train)
        
        # Evaluate
        y_pred = self.model.predict(X_test)
        accuracy = accuracy_score(y_test, y_pred)
        
        print(f"\n🎯 RESULTS:")
        print(f"✅ Accuracy: {accuracy:.2%}")
        print(f"📈 Toxic Detection Rate: {(y_pred[y_test==1].sum()/y_test.sum()):.1%}")
        
        # Save
        joblib.dump(self, 'feedspace_toxic_model.pkl')
        print("\n💾 Model saved: feedspace_toxic_model.pkl")
        return True
    
    def predict(self, texts):
        texts = [str(t) for t in texts]
        clean_texts = [self.preprocess(t) for t in texts]
        X_vec = self.vectorizer.transform(clean_texts)
        predictions = self.model.predict(X_vec)
        probabilities = self.model.predict_proba(X_vec)[:, 1]
        
        return [{
            'original': texts[i],
            'cleaned': clean_texts[i],
            'is_toxic': bool(predictions[i]),
            'toxicity_score': float(probabilities[i]),
            'confidence': 1.0 - min(probabilities[i], 1-probabilities[i])
        } for i in range(len(texts))]

# RUN TRAINING
if __name__ == "__main__":
    detector = ToxicDetector()
    
    if detector.train("labeled_data.csv"):
        print("\n🧪 Testing Feedspace posts:")
        
        detector = joblib.load('feedspace_toxic_model.pkl')
        tests = [
            "I hate this school so much!",
            "Great math homework help",
            "You are a complete idiot!",
            "Normal helpful post",
            "SPAM BUY NOW CLICK HERE!!!"
        ]
        
        results = detector.predict(tests)
        for r in results:
            status = "🚨 TOXIC" if r['is_toxic'] else "✅ SAFE"
            score = f"{r['toxicity_score']:.0%}"
            print(f"{status} [{score}] {r['original']}")
        
        print("\n🎉 Ready for PHP integration!")